<?php

declare(strict_types=1);

namespace Akawaka\SyliusSogeCommercePlugin\Exception;

class SogeCommerceApiNotActivatedException extends SogeCommerceApiException
{
    public function __construct(string $message = 'Api not activated, see https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/api/get_my_keys.html', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
