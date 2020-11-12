<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\REST\Client;

class LinkType
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;

    /** @var string[] */
    protected $update = [];

    /**
     * Initialize LinkType object on data obtained from API
     *
     * @param Client    $jiraClient   JIRA API client to use.
     * @param \stdClass $LinkTypeInfo Issue link type information received from JIRA API.
     *
     * @return static
     */
    public static function fromStdClass(Client $jiraClient, \stdClass $LinkTypeInfo): LinkType
    {
        $Instance = new static($jiraClient, $LinkTypeInfo->id);
        $Instance->OriginalObject = $LinkTypeInfo;

        return $Instance;
    }

    /**
     * Get LinkType info from API by ID.
     *
     * This method makes an API request immediately, while
     *     $LinkType = new LinkType(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $LinkType->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of link type you want to get.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, int $id): LinkType
    {
        $Instance = new static($jiraClient, $id);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(Client $Jira, int $id = 0)
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
            $this->OriginalObject = $this->Jira->issueLinkType()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    /**
     * Drop internal object cache.
     *
     * @return $this
     */
    public function dropCache()
    {
        $this->OriginalObject = null;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->getOriginalObject()->name;
    }

    public function setName(string $new_name): LinkType
    {
        $this->update['name'] = $new_name;

        return $this;
    }

    public function getInward()
    {
        return $this->getOriginalObject()->inward;
    }

    public function setInward(string $new_inward_description): LinkType
    {
        $this->update['inward'] = $new_inward_description;

        return $this;
    }

    public function getOutward()
    {
        return $this->getOriginalObject()->outward;
    }

    public function setOutward(string $new_outward_description): LinkType
    {
        $this->update['outward'] = $new_outward_description;

        return $this;
    }

    /**
     * @throws \Mekras\Jira\REST\Exception
     */
    public function save(): LinkType
    {
        if (empty($this->update) && $this->getId() !== 0) {
            return $this;
        }

        if ($this->getId() === 0) {
            $LinkTypeInfo = $this->Jira->issueLinkType()->create(
                $this->update['name'] ?? '',
                $this->update['inward'] ?? '',
                $this->update['outward'] ?? ''
            );

            $this->id = $LinkTypeInfo->id;
        } else {
            $LinkTypeInfo = $this->Jira->issueLinkType()->update(
                $this->getId(),
                $this->update['name'] ?? '',
                $this->update['inward'] ?? '',
                $this->update['outward'] ?? ''
            );
        }


        $this->dropCache();
        $this->OriginalObject = $LinkTypeInfo;
        $this->update = [];

        return $this;
    }

    public function delete(): LinkType
    {
        if ($this->getId() === 0) {
            return $this;
        }

        $this->Jira->issueLinkType()->delete($this->getId());

        return $this;
    }
}
