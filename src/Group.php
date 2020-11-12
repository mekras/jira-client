<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class Group
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var string */
    protected $name;    // Developers

    /** @var string */
    protected $self;    // https://<jira host>/rest/api/2/group?groupname=Developers"

    /** @var User[] */
    protected $users;

    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $GroupInfo
    ): Group {
        $Instance = new static($GroupInfo->name, $jiraClient);
        $Instance->init($GroupInfo);

        return $Instance;
    }

    public function __construct(string $name, Client $jiraClient)
    {
        $this->name = $name;
        $this->Jira = $jiraClient;
    }

    public function __toString()
    {
        return $this->getName();
    }

    protected function init(\stdClass $GroupInfo)
    {
        $this->OriginalObject = $GroupInfo;

        $this->name = $GroupInfo->name;
        $this->self = $GroupInfo->self;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get all users in group
     * WARNING: for large groups can take long time
     *
     * @see \Mekras\Jira\REST\Section\Group::listAllUsers for more information
     */
    public function getAllUsers(): array
    {
        if (!isset($this->users)) {
            $users = $this->Jira->group()->listAllUsers($this->getName());

            $this->users = [];
            foreach ($users as $UserInfo) {
                $User = User::fromStdClass($this->Jira, $UserInfo);
                $this->users[$User->getName()] = $User;
            }
        }

        return $this->users;
    }
}
