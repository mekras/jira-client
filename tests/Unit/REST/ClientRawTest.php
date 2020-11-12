<?php

declare(strict_types=1);

namespace Mekras\Jira\Tests\Unit\REST;

use Mekras\Jira\REST\ClientRaw;
use Mekras\Jira\REST\HTTP\HttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for ClientRaw.
 *
 * @covers \Mekras\Jira\REST\ClientRaw
 */
class ClientRawTest extends TestCase
{
    /**
     * @var CacheInterface&MockObject
     */
    private $cache;

    /**
     * @var HttpClient&MockObject
     */
    private $httpClient;

    /**
     * @var TestLogger
     */
    private $logger;

    /**
     * @var ClientRaw
     */
    private $rawClient;

    /**
     * Tests that specified HTTP requests are not cached.
     *
     * @throws \Throwable
     */
    public function testNonCacheableRequest(): void
    {
        // To store used cache key.
        $cacheKey = null;

        $this->cache->expects(self::never())->method('get');
        $this->cache->expects(self::never())->method('has');
        $this->cache->expects(self::never())->method('set');

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                self::equalTo('POST'),
                self::equalTo('https://jira.localhost/rest/api/latest/foo'),
                self::equalTo(''),
                self::equalTo(''),
                self::equalTo([])
            )
            ->willReturnCallback(
                static function ($a, $b, $c, $d, $e, &$info): string {
                    $info['http_code'] = 200;
                    $info['content_type'] = 'application/json';

                    return '{"foo":"bar"}';
                }
            );

        $expected = new \stdClass();
        $expected->foo = 'bar';

        self::assertEquals($expected, $this->rawClient->post('/foo'));

        self::assertEquals(
            [
                [
                    'level' => 'debug',
                    'message' => 'Method "POST https://jira.localhost/rest/api/latest/foo" requested.',
                    'context' => [],
                ],
                [
                    'level' => 'debug',
                    'message' => 'Sending request to Jira API…',
                    'context' => [],
                ],
            ],
            $this->logger->records
        );
    }

    /**
     * Tests that HTTP requests results are cached.
     *
     * @throws \Throwable
     */
    public function testRequestCaching(): void
    {
        // To store used cache key.
        $cacheKey = null;

        $this->cache
            ->expects(self::at(0))
            ->method('has')
            ->with(self::anything())
            ->willReturn(false);

        $this->httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                self::equalTo('GET'),
                self::equalTo('https://jira.localhost/rest/api/latest/foo'),
                self::equalTo(''),
                self::equalTo(''),
                self::equalTo([])
            )
            ->willReturnCallback(
                static function ($a, $b, $c, $d, $e, &$info): string {
                    $info['http_code'] = 200;
                    $info['content_type'] = 'application/json';

                    return '{"foo":"bar"}';
                }
            );

        $this->cache
            ->expects(self::once())
            ->method('set')
            ->with(
                self::callback(
                    static function (string $key) use (&$cacheKey): bool {
                        $cacheKey = $key;

                        return true;
                    }
                ),
                self::equalTo(
                    [
                        'body' => '{"foo":"bar"}',
                        'content_type' => 'application/json',
                        'http_code' => 200,
                    ]
                )
            );

        $this->cache
            ->expects(self::at(2))
            ->method('has')
            ->with(
                self::callback(
                    static function (string $key) use (&$cacheKey): bool {
                        self::assertEquals($cacheKey, $key);

                        return true;
                    }
                )
            )
            ->willReturn(true);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                self::callback(
                    static function (string $key) use (&$cacheKey): bool {
                        self::assertEquals($cacheKey, $key);

                        return true;
                    }
                )
            )
            ->willReturn(
                [
                    'body' => '{"foo":"bar"}',
                    'content_type' => 'application/json',
                    'http_code' => 200,
                ]
            );

        $expected = new \stdClass();
        $expected->foo = 'bar';

        self::assertEquals($expected, $this->rawClient->get('/foo'));
        self::assertEquals($expected, $this->rawClient->get('/foo'));

        self::assertEquals(
            [
                [
                    'level' => 'debug',
                    'message' => 'Method "GET https://jira.localhost/rest/api/latest/foo" requested.',
                    'context' => [],
                ],
                [
                    'level' => 'debug',
                    'message' => 'Sending request to Jira API…',
                    'context' => [],
                ],
                [
                    'level' => 'debug',
                    'message' => 'Method "GET https://jira.localhost/rest/api/latest/foo" requested.',
                    'context' => [],
                ],
                [
                    'level' => 'debug',
                    'message' => 'Using cached response.',
                    'context' => [],
                ],
            ],
            $this->logger->records
        );
    }

    /**
     * Prepare test environment.
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = $this->createMock(HttpClient::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = new TestLogger();

        $this->rawClient = new ClientRaw(
            'https://jira.localhost/',
            '/rest/api/latest/',
            $this->httpClient,
            $this->cache,
            $this->logger
        );
    }
}
