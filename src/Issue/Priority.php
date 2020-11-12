<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class Priority
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var Issue */
    protected $Issue;

    /**
     * Initialize Priority object on data obtained from API
     *
     * @param Client    $jiraClient   JIRA API client to use.
     * @param \stdClass $PriorityInfo Issue priority information received from JIRA API.
     * @param Issue     $Issue        When current Priority object represents current priority of some issue.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $PriorityInfo,
        Issue $Issue = null
    ): Priority {
        $Instance = new static($jiraClient, (int) $PriorityInfo->id);

        $Instance->OriginalObject = $PriorityInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Priority info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Priority = new Priority(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Priority->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of priority you want to get.
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

    public function __construct(Client $jiraClient, int $id)
    {
        $this->Jira = $jiraClient;
        $this->id = $id;
    }

    /**
     * @return \stdClass
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->priority()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    public function getIssue(): ?Issue
    {
        return $this->Issue;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->getOriginalObject()->name;
    }

    public function getIconUrl(): string
    {
        return $this->getOriginalObject()->iconUrl ?? '';
    }

    public function getSelf(): string
    {
        return $this->getOriginalObject()->self;
    }
}
