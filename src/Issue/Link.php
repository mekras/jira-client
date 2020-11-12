<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\REST\Client;

class Link
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var array */
    protected $cache = [];

    /**
     * Initialize Link object on data obtained from API
     *
     * @param Client    $jiraClient JIRA API client to use.
     * @param \stdClass $LinkInfo   Issue link information received from JIRA API.
     *
     * @return static
     */
    public static function fromStdClass(Client $jiraClient, \stdClass $LinkInfo): Link
    {
        $Instance = new static($jiraClient, $LinkInfo->id);
        $Instance->OriginalObject = $LinkInfo;

        return $Instance;
    }

    /**
     * Issue link info, returned by JIRA API in issue fields has only one issue (inward or outward)
     * information. That is because the second end is always current issue itself.
     *
     * Because of this optimization we have to hack initializer to return second issue info without
     * requesting API once again.
     *
     * @param \stdClass          $LinkInfo
     * @param \Mekras\Jira\Issue $Issue
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Link
     */
    public static function fromIssueField(\stdClass $LinkInfo, \Mekras\Jira\Issue $Issue): Link
    {
        $Instance = static::fromStdClass($LinkInfo, $Issue->getJiraClient());

        if (isset($LinkInfo->inwardIssue) && isset($LinkInfo->outwardIssue)) {
            throw new \Mekras\Jira\Exception\Link(
                "Wrong method usage. Both inward and outward issues defined in LinkInfo object. Use ::fromStdClass() method instead"
            );
        }

        if (isset($LinkInfo->inwardIssue)) {
            $cache_key = 'OutwardIssue';
        } else {
            $cache_key = 'InwardIssue';
        }

        $Instance->cache[$cache_key] = $Issue;

        return $Instance;
    }

    /**
     * Issue link info, returned by JIRA API in issue fields has only one issue (inward or outward)
     * information. That is because the second end is always current issue itself.
     *
     * Because of this optimization we have to hack initializer to return second issue info without
     * requesting API once again.
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param string $type
     * @param string $outwardIssue
     * @param string $inwardIssue
     * @param string $comment
     * @param array  $visibility
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Link
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\IssueLink::create() for parameters description
     *
     */
    public static function create(
        Client $jiraClient,
        string $type,
        string $outwardIssue,
        string $inwardIssue,
        string $comment = '',
        array $visibility = []
    ): Link {
        $jiraClient->issueLink()->create($type, $outwardIssue, $inwardIssue, $comment, $visibility);

        // actualize key, we can't be sure issue was not renamed some time ago
        $inwardIssue = $jiraClient->issue()->get($inwardIssue, ['key'])->key;

        $links = $jiraClient->issueLink()->listForIssue($outwardIssue, $type);
        foreach ($links as $LinkInfo) {
            if ($LinkInfo->inwardIssue->key === $inwardIssue) {
                return static::get(
                    $LinkInfo->id,
                    $jiraClient
                ); // we have to get it because of half data in links info :(
            }
        }

        throw new \Mekras\Jira\Exception\Link(
            "Failed to create new link or load its info from API after creation"
        );
    }

    /**
     * Get Link info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Link = new Link(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Link->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of link you want to get.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, int $id)
    {
        $Instance = new static($jiraClient, $id);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(Client $Jira, int $id)
    {
        $this->id = $id;
        $this->Jira = $Jira;
    }

    /**
     * @return \stdClass
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject(): \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->issueLink()->get($this->id);
        }

        return $this->OriginalObject;
    }

    /**
     * @param \stdClass $IssueInfo
     *
     * @return \Mekras\Jira\Issue
     */
    protected function getIssueFromLinkInfo(\stdClass $IssueInfo): \Mekras\Jira\Issue
    {
        return \Mekras\Jira\Issue::fromStdClass(
            $IssueInfo,
            [
                'id',
                'key',
                'self',
                'summary',
                'status',
                'priority',
                'issuetype',
            ],
            [],
            $this->Jira
        );
    }

    /**
     * Drop internal object cache
     *
     * @return $this
     */
    public function dropCache(): Link
    {
        $this->OriginalObject = null;
        $this->cache = [];

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get link type information
     *
     * @return LinkType
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getType(): LinkType
    {
        $Type = $this->cache['Type'] ?? null;
        if (!isset($Type)) {
            $Type = LinkType::fromStdClass($this->Jira, $this->getOriginalObject()->type);
            $this->cache['Type'] = $Type;
        }

        return $Type;
    }

    /**
     * Get inward issue of link
     *
     * @return \Mekras\Jira\Issue
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getInwardIssue()
    {
        $Issue = $this->cache['InwardIssue'] ?? null;
        if (!isset($Issue)) {
            $Issue = $this->getIssueFromLinkInfo($this->getOriginalObject()->inwardIssue);
            $this->cache['InwardIssue'] = $Issue;
        }

        return $Issue;
    }

    /**
     * Get outward issue of link
     *
     * @return \Mekras\Jira\Issue
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getOutwardIssue()
    {
        $Issue = $this->cache['OutwardIssue'] ?? null;
        if (!isset($Issue)) {
            $Issue = $this->getIssueFromLinkInfo($this->getOriginalObject()->outwardIssue);
            $this->cache['OutwardIssue'] = $Issue;
        }

        return $Issue;
    }

    public function delete(): Link
    {
        $this->Jira->issueLink()->delete($this->getId());

        return $this;
    }
}
