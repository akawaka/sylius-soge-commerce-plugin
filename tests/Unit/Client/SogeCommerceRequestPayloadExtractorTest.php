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

use Akawaka\SyliusSogeCommercePlugin\Client\SogeCommerceRequestPayloadExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SogeCommerceRequestPayloadExtractorTest extends TestCase
{
    public function testExtractFromParameterBagWhenSymfonyHasParsedFormEncodedBody(): void
    {
        $request = new Request();
        $request->request->replace([
            'kr-answer' => '{"transactions":[{"uuid":"abc"}]}',
            'kr-hash' => 'some-hash',
            'kr-hash-algorithm' => 'sha256_hmac',
        ]);

        $payload = (new SogeCommerceRequestPayloadExtractor())->extract($request);

        self::assertSame('{"transactions":[{"uuid":"abc"}]}', $payload->krAnswer);
        self::assertSame('some-hash', $payload->krHash);
        self::assertSame('sha256_hmac', $payload->krHashAlgorithm);
    }

    public function testExtractFromRawFormEncodedBodyWhenSymfonyHasNotParsedIt(): void
    {
        $request = new Request(
            content: 'kr-answer=' . rawurlencode('{"transactions":[{"uuid":"abc"}]}') . '&kr-hash=some-hash&kr-hash-algorithm=sha256_hmac',
        );

        $payload = (new SogeCommerceRequestPayloadExtractor())->extract($request);

        self::assertSame('{"transactions":[{"uuid":"abc"}]}', $payload->krAnswer);
        self::assertSame('some-hash', $payload->krHash);
        self::assertSame('sha256_hmac', $payload->krHashAlgorithm);
    }

    public function testExtractFromJsonBody(): void
    {
        $jsonBody = json_encode([
            'kr-answer' => '{"transactions":[{"uuid":"abc"}]}',
            'kr-hash' => 'some-hash',
            'kr-hash-algorithm' => 'sha256_hmac',
        ], \JSON_THROW_ON_ERROR);

        $request = new Request(content: $jsonBody);
        $request->headers->set('Content-Type', 'application/json');

        $payload = (new SogeCommerceRequestPayloadExtractor())->extract($request);

        self::assertSame('{"transactions":[{"uuid":"abc"}]}', $payload->krAnswer);
        self::assertSame('some-hash', $payload->krHash);
        self::assertSame('sha256_hmac', $payload->krHashAlgorithm);
    }

    public function testExtractFallsBackToRawBodyWhenParameterBagIsPartiallyFilled(): void
    {
        $request = new Request(
            content: 'kr-answer=' . rawurlencode('{"transactions":[]}') . '&kr-hash=some-hash&kr-hash-algorithm=sha256_hmac',
        );
        $request->request->replace([
            'kr-answer' => '{"transactions":[]}',
        ]);

        $payload = (new SogeCommerceRequestPayloadExtractor())->extract($request);

        self::assertSame('{"transactions":[]}', $payload->krAnswer);
        self::assertSame('some-hash', $payload->krHash);
        self::assertSame('sha256_hmac', $payload->krHashAlgorithm);
    }

    public function testExtractThrowsOnEmptyRequest(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SogeCommerceRequestPayloadExtractor())->extract(new Request());
    }

    public function testExtractThrowsWhenKrAnswerIsMissing(): void
    {
        $request = new Request();
        $request->request->replace([
            'kr-hash' => 'some-hash',
            'kr-hash-algorithm' => 'sha256_hmac',
        ]);

        $this->expectException(\RuntimeException::class);

        (new SogeCommerceRequestPayloadExtractor())->extract($request);
    }

    public function testExtractThrowsWhenKrHashIsMissing(): void
    {
        $request = new Request();
        $request->request->replace([
            'kr-answer' => '{}',
            'kr-hash-algorithm' => 'sha256_hmac',
        ]);

        $this->expectException(\RuntimeException::class);

        (new SogeCommerceRequestPayloadExtractor())->extract($request);
    }

    public function testExtractThrowsWhenKrHashAlgorithmIsMissing(): void
    {
        $request = new Request();
        $request->request->replace([
            'kr-answer' => '{}',
            'kr-hash' => 'some-hash',
        ]);

        $this->expectException(\RuntimeException::class);

        (new SogeCommerceRequestPayloadExtractor())->extract($request);
    }

    public function testExtractThrowsOnUnparseableBody(): void
    {
        $request = new Request(content: 'not a form, not a json, just binary garbage ###');

        $this->expectException(\RuntimeException::class);

        (new SogeCommerceRequestPayloadExtractor())->extract($request);
    }

    public function testDecodedAnswerReturnsArray(): void
    {
        $request = new Request();
        $request->request->replace([
            'kr-answer' => '{"transactions":[{"uuid":"abc"}],"orderDetails":{"orderId":"foo"}}',
            'kr-hash' => 'h',
            'kr-hash-algorithm' => 'sha256_hmac',
        ]);

        $payload = (new SogeCommerceRequestPayloadExtractor())->extract($request);
        $decoded = $payload->decodedAnswer();

        self::assertSame([
            'transactions' => [['uuid' => 'abc']],
            'orderDetails' => ['orderId' => 'foo'],
        ], $decoded);
    }
}
