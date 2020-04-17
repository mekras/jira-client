<?php

declare(strict_types=1);

namespace Badoo\Jira\UTests\REST\HTTP;

use Badoo\Jira\REST\HTTP\PsrHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests for PsrHttpClient
 *
 * @covers \Badoo\Jira\REST\HTTP\PsrHttpClient
 */
class PsrHttpClientTest extends TestCase
{
    /**
     * Jira user secret for requests.
     */
    private const JIRA_SECRET = 'secret';

    /**
     * Jira user name for requests.
     */
    private const JIRA_USER = 'username';

    /**
     * Expected value for HTTP basic authentication.
     *
     * @var string
     */
    private static $expectedBasicAuth;

    /**
     * HTTP client under test.
     *
     * @var PsrHttpClient
     */
    private $client;

    /**
     * PSR-18 HTTP client.
     *
     * @var ClientInterface&MockObject
     */
    private $httpClient;

    /**
     * Test sending requests using GET method.
     *
     * @throws \Throwable
     */
    public function testGetRequest(): void
    {
        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(
                self::callback(
                    static function (RequestInterface $request): bool {
                        self::assertEquals('GET', $request->getMethod());
                        self::assertEquals('http://example.com/foo', $request->getUri());
                        self::assertEquals(
                            self::$expectedBasicAuth,
                            $request->getHeaderLine('Authorization')
                        );
                        self::assertEquals(
                            'application/json',
                            $request->getHeaderLine('Content-Type')
                        );

                        return true;
                    }
                )
            )
            ->willReturn($this->createResponse());

        $response = $this->client->request(
            'GET',
            'http://example.com/foo',
            'username',
            'secret',
            ['foo' => 'bar'],
            $info
        );

        self::assertEquals(200, $info['http_code']);
        self::assertEquals('application/json', $info['content_type']);
        self::assertEquals('{"foo":"bar"}', $response);
    }

    /**
     * Test sending requests using POST method.
     *
     * @throws \Throwable
     */
    public function testPostRequest(): void
    {
        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(
                self::callback(
                    static function (RequestInterface $request): bool {
                        self::assertEquals('POST', $request->getMethod());
                        self::assertEquals('http://example.com/foo', $request->getUri());
                        self::assertEquals(
                            self::$expectedBasicAuth,
                            $request->getHeaderLine('Authorization')
                        );
                        self::assertEquals(
                            'application/json',
                            $request->getHeaderLine('Content-Type')
                        );
                        self::assertJsonStringEqualsJsonString(
                            '{"foo":"bar"}',
                            (string) $request->getBody()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($this->createResponse());

        $response = $this->client->request(
            'POST',
            'http://example.com/foo',
            'username',
            'secret',
            ['foo' => 'bar'],
            $info
        );

        self::assertEquals(200, $info['http_code']);
        self::assertEquals('application/json', $info['content_type']);
        self::assertEquals('{"foo":"bar"}', $response);
    }

    /**
     * Test sending requests using MULTIPART pseudo method.
     *
     * @throws \Throwable
     */
    public function testMultipartRequest(): void
    {
        $this->httpClient
            ->expects(self::once())
            ->method('sendRequest')
            ->with(
                self::callback(
                    static function (RequestInterface $request): bool {
                        self::assertEquals('POST', $request->getMethod());
                        self::assertEquals('http://example.com/foo', $request->getUri());
                        self::assertEquals(
                            self::$expectedBasicAuth,
                            $request->getHeaderLine('Authorization')
                        );
                        self::assertRegExp(
                            '~multipart/form-data; boundary=".+"~',
                            $request->getHeaderLine('Content-Type')
                        );
                        self::assertStringContainsString(
                            'name="foo"',
                            (string) $request->getBody()
                        );
                        self::assertStringContainsString(
                            "bar\r\n",
                            (string) $request->getBody()
                        );

                        return true;
                    }
                )
            )
            ->willReturn($this->createResponse());

        $response = $this->client->request(
            'MULTIPART',
            'http://example.com/foo',
            'username',
            'secret',
            ['foo' => 'bar'],
            $info
        );

        self::assertEquals(200, $info['http_code']);
        self::assertEquals('application/json', $info['content_type']);
        self::assertEquals('{"foo":"bar"}', $response);
    }

    /**
     * Set up test environment.
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$expectedBasicAuth = 'Basic '
            . base64_encode(self::JIRA_USER . ':' . self::JIRA_SECRET);

        $psr7Factory = new Psr17Factory();
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->client = new PsrHttpClient(
            $this->httpClient,
            $psr7Factory,
            $psr7Factory
        );
    }

    /**
     * Create HTTP response.
     *
     * @param string $body Plain response body.
     *
     * @return ResponseInterface
     */
    private function createResponse(string $body = '{"foo":"bar"}'): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], $body);
    }
}
