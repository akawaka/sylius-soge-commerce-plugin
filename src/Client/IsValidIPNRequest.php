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

final class IsValidIPNRequest implements IsValidRequestInterface
{
    public function __construct(
        private readonly SogeCommerceRequestPayloadExtractorInterface $payloadExtractor,
    ) {
    }

    public function __invoke(PaymentMethodInterface $method, Request $request): bool
    {
        $gatewayConfig = $method->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        try {
            $payload = $this->payloadExtractor->extract($request);
        } catch (\Throwable) {
            return false;
        }

        if ('sha256_hmac' !== $payload->krHashAlgorithm) {
            return false;
        }

        $key = $gatewayConfig->getConfig()['password'] ?? null;
        if (!is_string($key)) {
            return false;
        }

        $answer = str_replace('\/', '/', $payload->krAnswer);

        return hash_equals(hash_hmac('sha256', $answer, $key), $payload->krHash);
    }
}
