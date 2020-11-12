<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Priority extends Section
{
    /**
     * @var bool
     */
    private $allCached = false;

    /**
     * @var \stdClass[]
     */
    private $priorities_list = [];

    /**
     * Get particular priority info
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/priority-getPriority
     *
     * @param int  $id           - ID of priority
     * @param bool $reload_cache - force API request to load fresh data
     *
     * @return \stdClass|\stdClass[]|string|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false): \stdClass
    {
        $PriorityInfo = $this->priorities_list[$id] ?? null;

        if (!isset($PriorityInfo) || $reload_cache) {
            $PriorityInfo = $this->rawClient->get("priority/{$id}");
            $this->cachePriority($PriorityInfo);
        }

        return $PriorityInfo;
    }

    /**
     * List all known issue priorities
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/priority-getPriorities
     *
     * @param bool $reload_cache - force API request to load fresh data
     *
     * @return \stdClass[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list($reload_cache = false): array
    {
        if (!$this->allCached || $reload_cache) {
            $this->priorities_list = [];
            foreach ($this->rawClient->get('priority') as $PriorityInfo) {
                $this->cachePriority($PriorityInfo);
            }
            $this->allCached = true;
        }

        return $this->priorities_list;
    }

    /**
     * Search priority by name.
     *
     * NOTE: this is synthetic method, JIRA API has no special method searching priorities by name
     *       The full list of priorities is loaded before search.
     *
     * @param string $priority_name  - desired priority name
     * @param bool   $case_sensitive - perform case sensitive search. True by default
     * @param bool   $reload_cache   - ignore internal client cache and request JIRA API for fresh
     *                               data
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     *
     * @see Priority::list()
     *
     */
    public function searchByName(
        string $priority_name,
        bool $case_sensitive = true,
        bool $reload_cache = false
    ): ?\stdClass {
        $priorities = $this->list($reload_cache);

        if ($case_sensitive) {
            foreach ($priorities as $PriorityInfo) {
                if ($PriorityInfo->name === $priority_name) {
                    return $PriorityInfo;
                }
            }

            return null;
        }

        $priority_name = strtolower($priority_name);

        foreach ($priorities as $PriorityInfo) {
            if (strtolower($PriorityInfo->name) === $priority_name) {
                return $PriorityInfo;
            }
        }

        return null;
    }

    private function cachePriority(\stdClass $PriorityInfo)
    {
        $this->priorities_list[(int) $PriorityInfo->id] = $PriorityInfo;
    }
}
