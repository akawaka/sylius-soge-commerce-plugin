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

use Symfony\Component\HttpFoundation\Request;

final class SogeCommerceRequestPayloadExtractor implements SogeCommerceRequestPayloadExtractorInterface
{
    public function extract(Request $request): SogeCommerceRequestPayload
    {
        $payload = $this->fromParameterBag($request)
            ?? $this->fromRawFormEncoded($request)
            ?? $this->fromJsonBody($request);

        if (null === $payload) {
            throw new \RuntimeException(sprintf(
                'Unable to extract Soge Commerce payload from request (Content-Type: "%s"). Required fields: kr-answer, kr-hash, kr-hash-algorithm.',
                $request->headers->get('Content-Type', 'none'),
            ));
        }

        return $payload;
    }

    private function fromParameterBag(Request $request): ?SogeCommerceRequestPayload
    {
        /** @var array<string, mixed> $data */
        $data = $request->request->all();

        return self::buildFromArray($data);
    }

    private function fromRawFormEncoded(Request $request): ?SogeCommerceRequestPayload
    {
        $body = (string) $request->getContent();
        if ('' === $body) {
            return null;
        }

        /** @var array<string, mixed> $parsed */
        $parsed = [];
        parse_str($body, $parsed);

        return self::buildFromArray($parsed);
    }

    private function fromJsonBody(Request $request): ?SogeCommerceRequestPayload
    {
        $body = (string) $request->getContent();
        if ('' === $body) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return null;
        }

        return self::buildFromArray($decoded);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function buildFromArray(array $data): ?SogeCommerceRequestPayload
    {
        $krAnswer = $data['kr-answer'] ?? null;
        $krHash = $data['kr-hash'] ?? null;
        $krHashAlgorithm = $data['kr-hash-algorithm'] ?? null;

        if (!is_string($krAnswer) || '' === $krAnswer) {
            return null;
        }
        if (!is_string($krHash) || '' === $krHash) {
            return null;
        }
        if (!is_string($krHashAlgorithm) || '' === $krHashAlgorithm) {
            return null;
        }

        return new SogeCommerceRequestPayload(
            krAnswer: $krAnswer,
            krHash: $krHash,
            krHashAlgorithm: $krHashAlgorithm,
        );
    }
}
