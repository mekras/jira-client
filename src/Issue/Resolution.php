<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

class Resolution
{
    /** @var \Mekras\Jira\REST\Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var \Mekras\Jira\Issue */
    protected $Issue;

    /**
     * Initialize Resolution object on data obtained from API
     *
     * @param \stdClass $ResolutionInfo     - issue resolution information received from JIRA API.
     * @param \Mekras\Jira\Issue $Issue      - when current Resolution object represents current resolution of some issue.
     * @param \Mekras\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     */
    public static function fromStdClass(
        \stdClass $ResolutionInfo,
        \Mekras\Jira\Issue $Issue = null,
        \Mekras\Jira\REST\Client $Jira = null
    ) : Resolution {
        $Instance = new static((int)$ResolutionInfo->id, $Jira);

        $Instance->OriginalObject = $ResolutionInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get Resolution info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $Resolution = new Resolution(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call $Resolution->getName()).
     *
     * @param int $id                       - ID of resolution you want to get
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

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->resolution()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    public function getIssue() : ?\Mekras\Jira\Issue
    {
        return $this->Issue;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->getOriginalObject()->name;
    }

    public function getDescription() : string
    {
        return $this->getOriginalObject()->description ?? '';
    }

    public function getSelf() : string
    {
        return $this->getOriginalObject()->self;
    }
}
