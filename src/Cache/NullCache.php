<?php

declare(strict_types=1);

namespace Mekras\Jira\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache that doesn't cache anything.
 *
 * Used as a default cache to simplify cache related logic.
 *
 * @internal
 */
class NullCache implements CacheInterface
{
    /**
     * @inheritDoc
     */
    public function clear()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        return true;
    }
}
