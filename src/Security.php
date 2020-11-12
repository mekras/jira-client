<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class Security
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var int */
    protected $id;            // 10000

    /** @var Issue */
    protected $Issue;

    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $SecurityLevel,
        Issue $Issue = null
    ): Security {
        $Instance = new static((int) $SecurityLevel->id, $jiraClient);

        $Instance->OriginalObject = $SecurityLevel;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    public static function get(Client $jiraClient, int $id)
    {
        $Instance = new static($id, $jiraClient);
        $Instance->getOriginalObject();

        return $Instance;
    }

    public function __construct(int $id, Client $Jira)
    {
        $this->id = $id;
        $this->Jira = $Jira;
    }

    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->securityLevel()->get($this->getId());
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
