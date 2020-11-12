<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class SecurityLevel extends Section
{
    /**
     * @var array
     */
    private $securityLevelsList = [];

    /**
     * Get particular security level info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/securitylevel-getIssuesecuritylevel
     *
     * @param int  $id           - ID of security level you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false): \stdClass
    {
        $SecurityLevelInfo = $this->securityLevelsList[$id] ?? null;

        if (!isset($SecurityLevelInfo) || $reload_cache) {
            $SecurityLevelInfo = $this->rawClient->get("/securitylevel/{$id}");
            $this->cacheSecurityLevelInfo($SecurityLevelInfo);
        }

        return $SecurityLevelInfo;
    }

    private function cacheSecurityLevelInfo(\stdClass $SecurityLevelInfo): void
    {
        $this->securityLevelsList[(int) $SecurityLevelInfo->id] = $SecurityLevelInfo;
    }
}
