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

interface SogeCommerceRequestPayloadExtractorInterface
{
    /**
     * Extracts the kr-* fields from an IPN or bank return request, regardless of
     * the wire format actually received (form-encoded, raw form-encoded with a
     * non-standard Content-Type, or JSON body).
     *
     * @throws \RuntimeException when no strategy can recover the required fields
     */
    public function extract(Request $request): SogeCommerceRequestPayload;
}
