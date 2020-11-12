<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class UsersList
{
    /** @var Client */
    protected $Jira;

    /** @var Issue */
    protected $Issue;

    /** @var User[] */
    protected $by_name = [];
    /** @var User[] */
    protected $by_email = [];

    public static function fromStdClass(Client $jiraClient, array $usersInfo, Issue $Issue = null) : UsersList
    {
        $users = [];
        foreach ($usersInfo as $UserInfo) {
            $users[] = User::fromStdClass($jiraClient, $UserInfo, $Issue);
        }

        $Instance = new static($users, $jiraClient);
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * @param User[] $users      Users in list.
     * @param Client $jiraClient JIRA API client to use.
     */
    public function __construct(array $users, Client $jiraClient)
    {
        $this->addUsers(...$users);
        $this->Jira = $jiraClient;
    }

    /**
     * Clear list from all users
     *
     * @return $this
     */
    public function clearList() : UsersList
    {
        $this->by_name = [];
        $this->by_email = [];

        return $this;
    }

    /**
     * Get full list of users
     *
     * @return User[]
     */
    public function getUsers() : array
    {
        return $this->by_name ?? [];
    }

    /**
     * Add user to list
     *
     * @param User ...$users
     *
     * @return $this
     */
    public function addUsers(User ...$users) : UsersList
    {
        foreach ($users as $User) {
            $this->by_name[$User->getName()] = $User;
            $this->by_email[$User->getEmail()] = $User;
        }
        return $this;
    }

    /**
     * @param User ...$users
     * @return UsersList
     */
    public function removeUsers(User ...$users) : UsersList
    {
        foreach ($users as $User) {
            // Check if we have user in caches. If not - it is better not to call ->getEmail or other methods to not
            // trigger useless background API requests under $User object.
            if ($this->hasName($User->getName())) {
                unset($this->by_name[$User->getName()]);
                unset($this->by_email[$User->getEmail()]);
            }
        }

        return $this;
    }

    public function hasName(string $name) : bool
    {
        return isset($this->by_name[$name]);
    }

    public function hasEmail(string $email) : bool
    {
        return isset($this->by_email[$email]);
    }
}
