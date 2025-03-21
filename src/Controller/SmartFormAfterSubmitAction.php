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
use Akawaka\SyliusSogeCommercePlugin\Handler\ProgressOrderStatusHandlerInterface;
use Akawaka\SyliusSogeCommercePlugin\Handler\UpdateOrderPaymentMethodHandlerInterface;
use Doctrine\Persistence\ObjectManager;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

final class SmartFormAfterSubmitAction extends AbstractController
{
    public function __construct(
        private IsValidRequestInterface $isValidRequest,
        private SogeCommerceGatewayInterface $gateway,
        private OrderIdTransformerInterface $orderIdTransformer,
        private UpdateOrderPaymentMethodHandlerInterface $updateOrderPaymentMethodHandler,
        private ProgressOrderStatusHandlerInterface $progressOrderStatusHandler,
        private OrderRepositoryInterface $orderRepository,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ObjectManager $em,
    ) {
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
     * To prevent this, the StatusAction checks if the paid amount matches the current cart total.
     * If there is a mismatch, the payment is marked as failed and canceled using the Soge Commerce API.
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
        $this->updateOrderPaymentMethodHandler->__invoke($method, $order);

        if (false === $this->gateway->isPaymentSuccess($requestData)) {
            $this->em->flush();

            $this->addFlash('error', 'akawaka_sylius_soge_commerce_plugin.payment_refused');

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

    private function getRequestData(Request $request): array
    {
        $requestData = json_decode((string) $request->request->get('kr-answer'), true);
        Assert::isArray($requestData);

        return $requestData;
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

        $order = $this->orderRepository->findOneBy(['id' => $id]);
        if (null === $order) {
            throw $this->createNotFoundException();
        }

        Assert::isInstanceOf($order, OrderInterface::class);

        return $order;
    }
}
