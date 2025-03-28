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

namespace Tests\Akawaka\SyliusSogeCommercePlugin\Unit\Client;

use Akawaka\SyliusSogeCommercePlugin\Client\IsValidBankReturnRequest;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;
use Symfony\Component\HttpFoundation\Request;

final class IsValidBankReturnRequestTest extends TestCase
{
    /**
     * @dataProvider provideIsValidBankReturn
     *
     * @param class-string<\Throwable>|null $expectedExceptionFQCN
     */
    public function testIsValidBankReturn(
        array $config,
        array $requestData,
        ?bool $expectedResult,
        ?string $expectedExceptionFQCN,
    ): void {
        $isValidBankReturnRequest = new IsValidBankReturnRequest();

        $method = new PaymentMethod();

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);
        $method->setGatewayConfig($gatewayConfig);

        $request = new Request();
        $request->request->replace($requestData);

        if (null !== $expectedResult) {
            self::assertEquals($expectedResult, $isValidBankReturnRequest->__invoke($method, $request));
        }

        if (null !== $expectedExceptionFQCN) {
            self::expectException($expectedExceptionFQCN);
            $isValidBankReturnRequest->__invoke($method, $request);
        }
    }

    public function provideIsValidBankReturn(): iterable
    {
        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'sha256_hmac',
                'kr-answer' => 'some_answer',
                'kr-hash' => 'some_hash',
            ],
            false,
            null,
        ];

        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'sha256_hmac',
                'kr-answer' => 'some_answer',
                'kr-hash' => '8434fd6a93d9d12f709ca5a47cea66f2b34bab2fb77c04fcf19f066b0ef139fa',
            ],
            true,
            null,
        ];

        yield [
            [
                'hmac_sha_256_key' => 'test',
            ],
            [
                'kr-hash-algorithm' => 'invalid algorithm',
                'kr-answer' => 'some_answer',
                'kr-hash' => '8434fd6a93d9d12f709ca5a47cea66f2b34bab2fb77c04fcf19f066b0ef139fa',
            ],
            null,
            \RuntimeException::class,
        ];
    }
}
