<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class Resolution
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
     * Initialize Resolution object on data obtained from API
     *
     * @param \stdClass $ResolutionInfo Issue resolution information received from JIRA API.
     * @param Issue     $Issue          When current Resolution object represents current
     *                                  resolution of some issue.
     * @param Client    $jiraClient     JIRA API client to use.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $ResolutionInfo,
        Issue $Issue = null
    ): Resolution {
        $Instance = new static($jiraClient, (int) $ResolutionInfo->id);

        $Instance->OriginalObject = $ResolutionInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Resolution info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Resolution = new Resolution(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Resolution->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of resolution you want to get.
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

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->resolution()->get($this->getId());
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

    public function getSelf(): string
    {
        return $this->getOriginalObject()->self;
    }
}
