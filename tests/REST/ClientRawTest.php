<?php

declare(strict_types=1);

namespace Badoo\Jira\UTests\REST;

use Badoo\Jira\REST\ClientRaw;
use Badoo\Jira\REST\HTTP\HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for ClientRaw
 *
 * @covers \Badoo\Jira\REST\ClientRaw
 */
class ClientRawTest extends TestCase
{
    /**
     * Tests that HTTP requests results are cached.
     *
     * @throws \Throwable
     */
    public function testRequestCaching(): void
    {
        $httpClient = $this->createMock(HttpClient::class);
        $cache = $this->createMock(CacheInterface::class);

        // To store used cache key.
        $cacheKey = null;

        $client = new ClientRaw();
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
    }
}