<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

/**
 * TODO ???
 *
 * @since x.x
 */
final class IssueLinkType extends Section
{
    /**
     * @var bool
     */
    private $allCached = false;

    /**
     * @var \stdClass[]
     */
    private $linkTypesList = [];

    /**
     * Create a link between two issues.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-createIssueLinkType
     *
     * @param string $name    - link type name
     * @param string $outward - text to display for inward issue
     * @param string $inward  - text to display for outward issue
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function create(
        string $name,
        string $inward,
        string $outward
    ): \stdClass {
        $args = [
            'name' => $name,
            'inward' => $inward,
            'outward' => $outward,
        ];

        $LinkTypeInfo = $this->rawClient->post('issueLinkType', $args);
        $this->cacheLinkType($LinkTypeInfo);

        return $LinkTypeInfo;
    }

    /**
     * Delete a link between issues
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLink-deleteIssueLink
     *
     * @param int $link_id - ID of link to delete
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function delete(int $link_id): void
    {
        $this->rawClient->delete("issueLinkType/{$link_id}");
    }

    /**
     * Get info for specific link type
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-getIssueLinkType
     *
     * @param int  $link_type_id - ID of link type to get
     * @param bool $reload_cache - ignore cache and load fresh data from API
     *
     * @return \stdClass - link type info, see ::create method DocBlock for format description.
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $link_type_id, bool $reload_cache = false): \stdClass
    {
        $LinkTypeInfo = $this->linkTypesList[$link_type_id] ?? null;
        if (!isset($LinkTypeInfo) || $reload_cache) {
            $LinkTypeInfo = $this->rawClient->get("issueLinkType/{$link_type_id}");
            $this->cacheLinkType($LinkTypeInfo);
        }

        return $LinkTypeInfo;
    }

    /**
     * Get list of all known issue types
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-getIssueLinkTypes
     *
     * @param bool $reload_cache - ignore cache and load fresh data from API
     *
     * @return \stdClass[] - list of all known issue types indexed by IDs
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false): array
    {
        if (!$this->allCached || $reload_cache) {
            $response = $this->rawClient->get('issueLinkType');

            foreach ($response->issueLinkTypes as $LinkTypeInfo) {
                $this->cacheLinkType($LinkTypeInfo);
            }

            $this->allCached = true;
        }

        return $this->linkTypesList;
    }

    /**
     * Update link type information
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-updateIssueLinkType
     *
     * @param int    $link_type_id - ID of link type to get
     * @param string $name         - new link type name. Empty string means 'do not update'.
     * @param string $outward      - new text to display for inward issue. Empty string means 'do
     *                             not update'.
     * @param string $inward       - new text to display for outward issue. Empty string means 'do
     *                             not update'.
     *
     * @return \stdClass - link type info, see ::create method DocBlock for format description.
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function update(
        int $link_type_id,
        string $name = '',
        string $inward = '',
        string $outward = ''
    ): \stdClass {
        $args = [
            'name' => $name,
            'inward' => $inward,
            'outward' => $outward,
        ];

        if (isset($name)) {
            $args['name'] = $name;
        }
        if (isset($inward)) {
            $args['inward'] = $inward;
        }
        if (isset($outward)) {
            $args['outward'] = $outward;
        }

        $LinkTypeInfo = $this->rawClient->put("issueLinkType/{$link_type_id}", $args);
        $this->cacheLinkType($LinkTypeInfo);

        return $this->rawClient->put("issueLinkType/{$link_type_id}", $args);
    }

    private function cacheLinkType(\stdClass $LinkTypeInfo): void
    {
        $this->linkTypesList[$LinkTypeInfo->id] = $LinkTypeInfo;
    }
}
