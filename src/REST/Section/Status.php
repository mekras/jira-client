<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Status extends Section
{
    /**
     * @var bool
     */
    private $allCached = false;

    /**
     * @var array
     */
    private $statuses_list = [];

    /**
     * Get particular status info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/status-getStatus
     *
     * @param int  $id           - ID of status you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false): \stdClass
    {
        $statusInfo = $this->statuses_list[$id] ?? null;

        if (!isset($statusInfo) || $reload_cache) {
            $statusInfo = $this->rawClient->get("/status/{$id}");
            $this->cachestatusInfo($statusInfo);
        }

        return $statusInfo;
    }

    /**
     * Get list of all statuses configured in current JIRA installation
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/status-getStatuses
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of statuses, indexed by IDs
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false): array
    {
        if (!$this->allCached || $reload_cache) {
            foreach ($this->rawClient->get('/status') as $StatusInfo) {
                $this->cachestatusInfo($StatusInfo);
            }
            $this->allCached = true;
        }

        return $this->statuses_list;
    }

    private function cachestatusInfo(\stdClass $StatusInfo): void
    {
        $this->statuses_list[(int) $StatusInfo->id] = $StatusInfo;
    }
}
