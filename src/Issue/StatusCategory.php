<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\REST\Client;

class StatusCategory
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /**
     * Initialize StatusCategory object on data obtained from API
     *
     * @param Client    $jiraClient   JIRA API client to use.
     * @param \stdClass $CategoryInfo Issue type information received from JIRA API.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $CategoryInfo
    ) : StatusCategory {
        $Instance = new static($jiraClient, (int)$CategoryInfo->id);

        $Instance->OriginalObject = $CategoryInfo;

        return $Instance;
    }

    /**
     * Get StatusCategory info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $StatusCategory = new StatusCategory(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call $StatusCategory->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of status category you want to get.
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
            $this->OriginalObject = $this->Jira->statusCategory()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getKey() : string
    {
        return $this->getOriginalObject()->key;
    }

    public function getName() : string
    {
        return $this->getOriginalObject()->name;
    }

    public function getColorName() : string
    {
        return $this->getOriginalObject()->colorName;
    }

    public function getSelf() : string
    {
        return $this->getOriginalObject()->self;
    }
}
