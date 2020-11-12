<?php

declare(strict_types=1);

namespace Mekras\Jira\Cache;

/**
 * Cache related utils.
 *
 * @internal
 */
final class CacheUtils
{
    /**
     * Compose cache key from a given data.
     *
     * @param mixed $source Some unique source data for key.
     *
     * @return string
     */
    public static function composeKey($source): string
    {
        $key = $source;

        switch (true) {
            case is_array($source):
                $key = 'array:' . implode('', array_keys($source)) . implode('', $source);
                break;
            // TODO objects, etc…
        }

        if (!is_string($key)) {
            $key = (string) $key;
        }

        if (strlen($key) > 40) {
            return sha1($key);
        }

        return $key;
    }
}
