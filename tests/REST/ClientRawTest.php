<?php

declare(strict_types=1);

namespace Mekras\Jira\Tests\REST;

use Mekras\Jira\REST\ClientRaw;
use Mekras\Jira\REST\HTTP\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for ClientRaw
 *
 * @covers \Mekras\Jira\REST\ClientRaw
 */
class ClientRawTest extends TestCase
{
    /**
     * Tests that specified HTTP requests are not cached.
     *
     * @throws \Throwable
     */
    public function testNonCacheableRequest(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $cache = $this->createMock(CacheInterface::class);
        $logger = new TestLogger();

        // To store used cache key.
        $cacheKey = null;

        $client = new ClientRaw(
            ClientRaw::DEFAULT_JIRA_URL,
            ClientRaw::DEFAULT_JIRA_API_PREFIX,
            $logger
        );

        $client
            ->setCache($cache)
            ->setHttpClient($httpClient);

        $cache->expects(self::never())->method('get');
        $cache->expects(self::never())->method('has');
        $cache->expects(self::never())->method('set');

        $httpClient
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

        self::assertEquals($expected, $client->post('/foo'));

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
            $logger->records
        );
    }

    /**
     * Tests that HTTP requests results are cached.
     *
     * @throws \Throwable
     */
    public function testRequestCaching(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $cache = $this->createMock(CacheInterface::class);
        $logger = new TestLogger();

        // To store used cache key.
        $cacheKey = null;

        $client = new ClientRaw(
            ClientRaw::DEFAULT_JIRA_URL,
            ClientRaw::DEFAULT_JIRA_API_PREFIX,
            $logger
        );
        $client
            ->setCache($cache)
            ->setHttpClient($httpClient);

        $cache
            ->expects(self::at(0))
            ->method('has')
            ->with(self::anything())
            ->willReturn(false);

        $httpClient
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

        $cache
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

        $cache
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

        $cache
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

        self::assertEquals($expected, $client->get('/foo'));
        self::assertEquals($expected, $client->get('/foo'));

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
            $logger->records
        );
    }
}
