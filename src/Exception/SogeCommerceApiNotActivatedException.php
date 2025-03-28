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

namespace Akawaka\SyliusSogeCommercePlugin\Exception;

class SogeCommerceApiNotActivatedException extends SogeCommerceApiException
{
    public function __construct(string $message = 'Api not activated, see https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/api/get_my_keys.html', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
