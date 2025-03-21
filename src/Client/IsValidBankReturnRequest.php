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

namespace Akawaka\SyliusSogeCommercePlugin\Client;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class IsValidBankReturnRequest implements IsValidRequestInterface
{
    public function __invoke(PaymentMethodInterface $method, Request $request): bool
    {
        $gatewayConfig = $method->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $hashAlgorithm = $request->request->get('kr-hash-algorithm');
        Assert::string($hashAlgorithm);
        if ('sha256_hmac' !== $hashAlgorithm) {
            throw new \RuntimeException(sprintf('Unsuported "%s" hash algorithm', $hashAlgorithm));
        }

        $key = $gatewayConfig->getConfig()['hmac_sha_256_key'] ?? null;
        Assert::string($key);

        $answer = str_replace('\/', '/', (string) $request->request->get('kr-answer'));

        return hash_hmac('sha256', $answer, $key) === $request->request->get('kr-hash');
    }
}
