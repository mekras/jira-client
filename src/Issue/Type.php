<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class Type
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var Issue */
    private $Issue;

    /**
     * Initialize Type object on data obtained from API
     *
     * @param Client    $jiraClient JIRA API client to use.
     * @param \stdClass $TypeInfo   Issue type information received from JIRA API.
     * @param Issue     $Issue      When current Type object represents current type of some issue.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $TypeInfo,
        Issue $Issue = null
    ): Type {
        $Instance = new static($jiraClient, (int) $TypeInfo->id);

        $Instance->OriginalObject = $TypeInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Type info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Type = new Type(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Type->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of type you want to get.
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
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->issueType()->get($this->getId());
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

    public function getDescription(): string
    {
        return $this->getOriginalObject()->description ?? '';
    }

    public function isSubtask(): bool
    {
        return $this->getOriginalObject()->subtask ?? false;
    }

    public function getSelf(): string
    {
        return $this->getOriginalObject()->self;
    }

    public function getIconUrl(): string
    {
        return $this->getOriginalObject()->iconUrl ?? '';
    }

    public function getAvatarId(): string
    {
        return $this->getOriginalObject()->avatarId ?? '';
    }
}
