<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

use Mekras\Jira\REST\ClientRaw;
use Mekras\Jira\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;

/**
 * TODO Describe.
 *
 * TODO Declare as abstract?
 *
 * @since x.x
 */
class Section
{
    /**
     * Raw Jira client.
     *
     * @var ClientRaw
     */
    protected $rawClient;

    /**
     * TODO ???
     *
     * @var Section[]
     */
    protected $sections = [];

    /**
     * Cache for received data.
     *
     * @var CacheInterface
     */
    private $cache;

    /**
     * Construct section.
     *
     * @param ClientRaw           $rawClient Raw Jira client.
     * @param CacheInterface|null $cache     Cache for received data.
     *
     * @since x.x Added argument $cache.
     */
    public function __construct(ClientRaw $rawClient, CacheInterface $cache = null)
    {
        $this->rawClient = $rawClient;
        $this->cache = $cache ?? new NullCache();
    }

    /**
     * Return cache for received data.
     *
     * @return CacheInterface
     *
     * @since x.x
     */
    protected function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * @param string $sectionKey   The unique section key for cache. This prevents twin
     *                             objects creation for the same section on each method call.
     * @param string $sectionClass Use special custom class for given section. E. g.
     *                             ->getSubSection('/issue',
     *                             '\Mekras\Jira\REST\Section\Issue') will initialize and return
     *                             \Mekras\Jira\REST\Section\Issue class for section /issue.
     *
     * @return self
     */
    protected function getSection(string $sectionKey, string $sectionClass): self
    {
        if (!isset($this->sections[$sectionKey])) {
            $Section = new $sectionClass($this->rawClient, $sectionKey);
            $this->sections[$sectionKey] = $Section;
        }

        return $this->sections[$sectionKey];
    }
}
