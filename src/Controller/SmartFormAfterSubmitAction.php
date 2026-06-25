<?php

/*
 * This file is part of akawaka/sylius-soge-commerce-plugin
 *
 * AKAWAKA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Controller;

use Akawaka\SyliusSogeCommercePlugin\Client\IsValidRequestInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\OrderIdTransformerInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceGatewayInterface;
use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceRequestPayloadExtractorInterface;
use Akawaka\SyliusSogeCommercePlugin\Event\PaymentCancelationFailedEvent;
use Akawaka\SyliusSogeCommercePlugin\Exception\SogeCommerceApiException;
use Akawaka\SyliusSogeCommercePlugin\Handler\ProgressOrderStatusHandlerInterface;
use Akawaka\SyliusSogeCommercePlugin\Handler\UpdateOrderPaymentMethodHandlerInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

final class SmartFormAfterSubmitAction extends AbstractController
{
    private const LOGGED_BODY_MAX_LENGTH = 2000;

    private LoggerInterface $logger;

    public function __construct(
        private IsValidRequestInterface $isValidRequest,
        private SogeCommerceGatewayInterface $gateway,
        private OrderIdTransformerInterface $orderIdTransformer,
        private UpdateOrderPaymentMethodHandlerInterface $updateOrderPaymentMethodHandler,
        private ProgressOrderStatusHandlerInterface $progressOrderStatusHandler,
        private OrderRepositoryInterface $orderRepository,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ObjectManager $em,
        private SogeCommerceRequestPayloadExtractorInterface $payloadExtractor,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * This action is triggered after the user pays for their cart on the payment selection page.
     * At this moment, we still have a cart, so it must be completed to be transformed into an order.
     *
     * We do not validate the payment yet; this is done in the StatusAction, just like any other
     * Sylius payment gateway. The CaptureAction does nothing; everything is handled in this
     * action because the payment is actually performed on the payment selection page instead of after
     * the cart is completed.
     *
     * This creates a potential issue: a customer can open multiple browser windows, start a payment
     * in one, then modify their cart in another. This could result in them paying an outdated amount
     * that no longer matches their cart total.
     *
     * To prevent this, we compare the amount actually paid at SogeCommerce against the current order
     * total before completing the order. On a mismatch the order is left untouched (still a cart),
     * the payment is canceled using the Soge Commerce API, and the customer is sent back to payment
     * selection. The StatusAction keeps the same check afterwards as a defense in depth.
     *
     * If the API is not active, an event is triggered instead. It is the responsibility of those
     * who install the plugin to listen for this event and take action, such as notifying the webmaster by email.
     */
    public function __invoke(Request $request): Response
    {
        $requestData = $this->getRequestData($request);

        $method = $this->getPaymentMethod($requestData);
        if (false === $this->isValidRequest->__invoke($method, $request)) {
            throw $this->createAccessDeniedException();
        }

        $order = $this->getOrder($requestData);

        $payment = $order->getLastPayment();
        if (null === $payment) {
            // The order no longer carries a payment to attach the SogeCommerce result to (e.g. the
            // payment was regenerated after the cart changed mid-payment). Fail cleanly instead of
            // letting a null payment bubble up as a 500.
            $this->logger->error('Soge Commerce return received but the order has no active payment.', [
                'orderId' => $order->getId(),
            ]);

            $this->addFlash('error', 'akawaka_sylius_soge_commerce_plugin.payment_refused');

            return $this->redirectToRoute('sylius_shop_checkout_select_payment');
        }

        $this->updateOrderPaymentMethodHandler->__invoke($method, $order);

        if (false === $this->gateway->isPaymentSuccess($requestData)) {
            $this->em->flush();

            $this->addFlash('error', 'akawaka_sylius_soge_commerce_plugin.payment_refused');

            return $this->redirectToRoute('sylius_shop_checkout_select_payment');
        }

        if (false === $this->isPaidAmountUpToDate($requestData, $order)) {
            // The cart total changed while the payment was being made (e.g. another browser tab
            // added an item). Never complete the order on an amount the customer did not pay:
            // cancel the SogeCommerce charge and send them back to payment selection.
            $this->cancelPayment($payment, $requestData);
            $this->em->flush();

            $this->addFlash('error', 'akawaka_sylius_soge_commerce_plugin.amount_mismatch');

            return $this->redirectToRoute('sylius_shop_checkout_select_payment');
        }

        try {
            $this->progressOrderStatusHandler->__invoke($order, $requestData);
            $this->em->flush();
        } catch (SMException) {
            throw new UnprocessableEntityHttpException();
        }

        return $this->redirectToRoute('sylius_shop_order_pay', ['tokenValue' => $order->getTokenValue()]);
    }

    /**
     * The amount the customer actually paid at SogeCommerce must still match the current order
     * total; otherwise the cart was changed while the payment was in progress.
     *
     * @param array<string, mixed> $requestData
     */
    private function isPaidAmountUpToDate(array $requestData, OrderInterface $order): bool
    {
        $orderDetails = $requestData['orderDetails'] ?? null;
        if (!is_array($orderDetails)) {
            return false;
        }

        $paidAmount = $orderDetails['orderTotalAmount'] ?? null;

        return is_int($paidAmount) && $paidAmount === $order->getTotal();
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function cancelPayment(PaymentInterface $payment, array $requestData): void
    {
        // cancelPayment() reads the transaction reference from the payment details, so the
        // SogeCommerce payload must be stored before requesting the cancellation.
        $payment->setDetails([
            SogeCommerceGatewayInterface::PAYMENT_DETAILS_REQUEST_DATA_KEY => $requestData,
        ]);

        try {
            $this->gateway->cancelPayment($payment);
        } catch (SogeCommerceApiException $exception) {
            // The charge could not be canceled automatically; notify so it can be refunded manually.
            $this->eventDispatcher->dispatch(new PaymentCancelationFailedEvent($exception, $payment));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(Request $request): array
    {
        try {
            return $this->payloadExtractor->extract($request)->decodedAnswer();
        } catch (\Throwable $exception) {
            $body = (string) $request->getContent();
            $truncated = strlen($body) > self::LOGGED_BODY_MAX_LENGTH;

            $this->logger->error('Soge Commerce SmartForm payload could not be processed.', [
                'reason' => $exception->getMessage(),
                'content_type' => $request->headers->get('Content-Type'),
                'body_length' => strlen($body),
                'body_excerpt' => $truncated ? substr($body, 0, self::LOGGED_BODY_MAX_LENGTH) . '…' : $body,
                'parameter_bag_keys' => array_keys($request->request->all()),
            ]);

            return [];
        }
    }

    private function getPaymentMethod(array $requestData): PaymentMethodInterface
    {
        Assert::isArray($requestData['transactions']);
        Assert::isArray($requestData['transactions'][0]);

        $methodCode = $requestData['transactions'][0]['metadata'][SogeCommerceGatewayInterface::METADATA_METHOD] ?? null;
        Assert::string($methodCode);

        $method = $this->paymentMethodRepository->findOneBy(['code' => $methodCode]);
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        return $method;
    }

    private function getOrder(array $requestData): OrderInterface
    {
        $orderId = $requestData['orderDetails']['orderId'] ?? null;
        Assert::string($orderId);

        $id = $this->orderIdTransformer->retrieve($orderId);

        $order = $this->orderRepository->find($id);
        if (null === $order) {
            throw $this->createNotFoundException();
        }

        Assert::isInstanceOf($order, OrderInterface::class);

        return $order;
    }
}
