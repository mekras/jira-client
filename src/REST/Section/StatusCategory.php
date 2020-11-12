<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class StatusCategory extends Section
{
    /**
     * @var bool
     */
    private $allCached = false;

    /**
     * @var array
     */
    private $categoriesList = [];

    /**
     * Get particular status category info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/statuscategory-getStatusCategory
     *
     * @param int  $id           - ID of statuscategory you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false): \stdClass
    {
        $StatusCategoryInfo = $this->categoriesList[$id] ?? null;

        if (!isset($StatusCategoryInfo) || $reload_cache) {
            $StatusCategoryInfo = $this->rawClient->get("/statuscategory/{$id}");
            $this->cacheStatusCategoryInfo($StatusCategoryInfo);
        }

        return $StatusCategoryInfo;
    }

    /**
     * Get list of all status categories configured in current JIRA installation
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/statuscategory-getStatusCategoryes
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of statuscategories, indexed by IDs
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false): array
    {
        if (!$this->allCached || $reload_cache) {
            foreach ($this->rawClient->get('/statuscategory') as $StatusCategoryInfo) {
                $this->cacheStatusCategoryInfo($StatusCategoryInfo);
            }
            $this->allCached = true;
        }

        return $this->categoriesList;
    }

    private function cacheStatusCategoryInfo(\stdClass $StatusCategoryInfo): void
    {
        $this->categoriesList[(int) $StatusCategoryInfo->id] = $StatusCategoryInfo;
    }
}
