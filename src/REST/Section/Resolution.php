<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Resolution extends Section
{
    /**
     * @var bool
     */
    private $allCached = false;

    /**
     * @var array
     */
    private $resolutionsList = [];

    /**
     * Get particular resolution info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/resolution-getResolution
     *
     * @param int  $id           - ID of resolution you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false): \stdClass
    {
        $ResolutionInfo = $this->resolutionsList[$id] || null;

        if (!isset($ResolutionInfo) || $reload_cache) {
            $ResolutionInfo = $this->rawClient->get("/resolution/{$id}");
            $this->cacheResolutionInfo($ResolutionInfo);
        }

        return $ResolutionInfo;
    }

    /**
     * Get list of all resolutions configured in current JIRA installation
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/resolution-getResolutions
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of resolutions, indexed by IDs
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false): array
    {
        if (!$this->allCached || $reload_cache) {
            foreach ($this->rawClient->get('/resolution') as $ResolutionInfo) {
                $this->cacheResolutionInfo($ResolutionInfo);
            }
            $this->allCached = true;
        }

        return $this->resolutionsList;
    }

    private function cacheResolutionInfo(\stdClass $ResolutionInfo): void
    {
        $this->resolutionsList[(int) $ResolutionInfo->id];
    }
}
