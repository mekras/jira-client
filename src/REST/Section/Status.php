<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

class Status extends Section
{
    /** @var array */
    protected $statuses_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cachestatusInfo(\stdClass $StatusInfo)
    {
        $this->statuses_list[(int)$StatusInfo->id] = $StatusInfo;
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
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            foreach ($this->jira->get('/status') as $StatusInfo) {
                $this->cachestatusInfo($StatusInfo);
            }
            $this->all_cached = true;
        }

        return $this->statuses_list;
    }

    /**
     * Get particular status info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/status-getStatus
     *
     * @param int $id - ID of status you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $statusInfo = $this->statuses_list[$id] ?? null;

        if (!isset($statusInfo) || $reload_cache) {
            $statusInfo = $this->jira->get("/status/{$id}");
            $this->cachestatusInfo($statusInfo);
        }

        return $statusInfo;
    }
}
