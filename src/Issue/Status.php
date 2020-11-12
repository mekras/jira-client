<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class Status
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var StatusCategory */
    protected $StatusCategory;

    /** @var Issue */
    protected $Issue;

    /**
     * Initialize Status object on data loaded from API
     *
     * @param Client $jiraClient    JIRA API client to use.
     * @param \stdClass $StatusInfo Status information received from JIRA API.
     * @param Issue $Issue          When current Status object represents current status of some
     *                              issue.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $StatusInfo,
        Issue $Issue = null
    ): Status {
        $Instance = new static($jiraClient, (int) $StatusInfo->id);

        $Instance->OriginalObject = $StatusInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Status info by ID.
     *
     * This method makes an API request immediately, while
     *     $Status = new Status(<id>, <Client);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Status->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of status to get from API.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, int $id)
    {
        $StatusInfo = $jiraClient->status()->get($id);

        return static::fromStdClass($jiraClient, $StatusInfo, null);
    }

    public function __construct(Client $jiraClient, int $id)
    {
        $this->Jira = $jiraClient;
        $this->id = $id;
    }

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->status()->get($this->getId());
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

    public function getStatusCategory(): StatusCategory
    {
        if (!isset($this->StatusCategory)) {
            $this->StatusCategory = StatusCategory::fromStdClass(
                $this->Jira,
                $this->getOriginalObject()->statusCategory
            );
        }

        return $this->StatusCategory;
    }

    public function getIconUrl(): string
    {
        return $this->getOriginalObject()->iconUrl;
    }

    public function getSelf(): string
    {
        return $this->getOriginalObject()->self;
    }
}
