<?php

declare(strict_types=1);

namespace Mekras\Jira\Tests\Unit\Cache;

use Mekras\Jira\Cache\CacheUtils;
use PHPUnit\Framework\TestCase;

/**
 * Тесты CacheUtils.
 *
 * @covers \Mekras\Jira\Cache\CacheUtils
 */
class CacheUtilsTest extends TestCase
{
    /**
     * Valid data provider for {@see testComposeKey()}.
     *
     * @return iterable<string, array>
     *
     * @throws \Throwable
     */
    public static function validDataProvider(): iterable
    {
        return [
            'string (short)' => [
                'source' => 'foo',
                'expected' => 'foo',
            ],
            'string (long)' => [
                'source' => str_repeat('x', 41),
                'expected' => sha1(str_repeat('x', 41)),
            ],
            'array (short)' => [
                'source' => ['foo' => 'bar'],
                'expected' => 'array:foobar',
            ],
            'array (long)' => [
                'source' => ['foo' => str_repeat('x', 40)],
                'expected' => sha1('array:foo' . str_repeat('x', 40)),
            ],
        ];
    }

    /**
     * Tests cache key composing.
     *
     * @param mixed  $source   Source data for key.
     * @param string $expected Expected key.
     *
     * @throws \Throwable
     *
     * @dataProvider validDataProvider
     */
    public function testComposeKey($source, string $expected): void
    {
        self::assertEquals($expected, CacheUtils::composeKey($source));
    }
}
