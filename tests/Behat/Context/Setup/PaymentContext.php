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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;

final class PaymentContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private ExampleFactoryInterface $paymentMethodExampleFactory,
        private ObjectManager $paymentMethodManager,
        private array $gatewayFactories,
        private NotificationCheckerInterface $notificationChecker,
    ) {
    }

    /**
     * @Given the store has (also) a payment method :paymentMethodName with a code :paymentMethodCode and Soge Commerce gateway
     */
    public function theStoreHasPaymentMethodWithCodeAndSogeCommerceGateway(
        string $paymentMethodName,
        string $paymentMethodCode,
    ): void {
        $paymentMethod = $this->createPaymentMethod($paymentMethodName, $paymentMethodCode, 'Soge Commerce');
        $paymentMethod->getGatewayConfig()?->setConfig([
            'user' => 'TEST',
            'password' => 'TEST',
            'public_key' => 'TEST',
            'hmac_sha_256_key' => 'TEST',
        ]);

        $this->paymentMethodManager->flush();
    }

    /**
     * @param string $name
     * @param string $code
     * @param string $gatewayFactory
     * @param string $description
     * @param bool $addForCurrentChannel
     * @param int|null $position
     *
     * @return PaymentMethodInterface
     */
    private function createPaymentMethod(
        $name,
        $code,
        $gatewayFactory,
        $description = '',
        $addForCurrentChannel = true,
        $position = null,
    ) {
        $gatewayFactory = array_search($gatewayFactory, $this->gatewayFactories, true);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodExampleFactory->create([
            'name' => ucfirst($name),
            'code' => $code,
            'description' => $description,
            'gatewayName' => $gatewayFactory,
            'gatewayFactory' => $gatewayFactory,
            'enabled' => true,
            'channels' => ($addForCurrentChannel && $this->sharedStorage->has('channel')) ? [$this->sharedStorage->get('channel')] : [],
        ]);

        if (null !== $position) {
            $paymentMethod->setPosition($position);
        }

        $this->sharedStorage->set('payment_method', $paymentMethod);
        $this->paymentMethodRepository->add($paymentMethod);

        return $paymentMethod;
    }

    /**
     * @Then I should be notified that my payment is completed
     */
    public function iShouldBeNotifiedThatMyPaymentIsCompleted(): void
    {
        $this->notificationChecker->checkNotification(
            'Payment has been completed.',
            NotificationType::info(),
        );
    }
}
