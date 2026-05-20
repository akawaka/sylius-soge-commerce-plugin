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

use Webmozart\Assert\Assert;

final class SogeCommerceRequestPayload
{
    public function __construct(
        public readonly string $krAnswer,
        public readonly string $krHash,
        public readonly string $krHashAlgorithm,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function decodedAnswer(): array
    {
        $decoded = json_decode($this->krAnswer, true);
        Assert::isArray($decoded);

        return $decoded;
    }
}
