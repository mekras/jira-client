<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

class StatusCategory
{
    /** @var \Mekras\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /**
     * Initialize StatusCategory object on data obtained from API
     *
     * @param \stdClass $CategoryInfo       - issue type information received from JIRA API.
     * @param \Mekras\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     */
    public static function fromStdClass(
        \stdClass $CategoryInfo,
        \Mekras\Jira\REST\Client $Jira = null
    ) : StatusCategory {
        $Instance = new static((int)$CategoryInfo->id, $Jira);

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
     * @param int $id                       - ID of status category you want to get
     * @param \Mekras\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(int $id, \Mekras\Jira\REST\Client $Jira = null)
    {
        $Instance = new static($id, $Jira);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(int $id, \Mekras\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Mekras\Jira\REST\Client::instance();
        }

        $this->id = $id;
        $this->Jira = $Jira;
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
