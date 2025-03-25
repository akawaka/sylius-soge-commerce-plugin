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

namespace Akawaka\SyliusSogeCommercePlugin\Twig;

use Akawaka\SyliusSogeCommercePlugin\Payum\PaymentGatewayFactory;
use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Webmozart\Assert\Assert;

final class GatewayConfigurationExtension extends AbstractExtension
{
    /**
     * @param EntityRepository<PaymentMethodInterface> $paymentMethodRepository
     */
    public function __construct(
        private EntityRepository $paymentMethodRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('akawaka_soge_commerce_public_key', [$this, 'getPublicKey']),
        ];
    }

    public function getPublicKey(): ?string
    {
        $method = $this->findPaymentMethod();

        $publicKey = $method?->getGatewayConfig()?->getConfig()['public_key'] ?? null;
        if (null === $method) {
            return null;
        }

        Assert::string($publicKey);

        return $publicKey;
    }

    private function findPaymentMethod(): ?PaymentMethodInterface
    {
        $method = $this->paymentMethodRepository->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'c')
            ->where('c.factoryName = :factoryName')
            ->setParameter('factoryName', PaymentGatewayFactory::FACTORY_NAME)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $method) {
            return null;
        }

        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        return $method;
    }
}
