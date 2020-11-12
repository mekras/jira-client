<?php
/**
 * @package REST
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;
use Mekras\Jira\User;
use Mekras\Jira\UsersList;

class WatchersList extends UsersList
{
    protected $initialized = false;

    protected $loaded = false;

    /**
     * @param Client     $jiraClient JIRA API client to use.
     * @param array      $usersInfo
     * @param Issue|null $Issue
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception
     */
    public static function fromStdClass(
        Client $jiraClient,
        array $usersInfo,
        Issue $Issue = null
    ): UsersList {
        if (!isset($Issue)) {
            throw new \Mekras\Jira\Exception(
                "Watchers list requires parent Issue object to work properly"
            );
        }

        $users = [];
        foreach ($usersInfo as $UserInfo) {
            $users[] = User::fromStdClass($jiraClient, $UserInfo, $Issue);
        }

        $Instance = new static($users, $jiraClient);
        $Instance->Issue = $Issue;
        $Instance->initialized = true;

        return $Instance;
    }

    /**
     * @param string $issueKey
     * @param Client $jiraClient JIRA API client to use.
     *
     * @return WatchersList
     *
     * @throws \Mekras\Jira\Exception
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function forIssue(Client $jiraClient, string $issueKey): WatchersList
    {
        $Issue = new Issue($jiraClient, $issueKey);

        return $Issue->getWatchers();
    }

    /**
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function clearList(): UsersList
    {
        if ($this->initialized) {
            foreach ($this->getUsers() as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            };
        }

        $this->loaded = false;

        return UsersList::clearList();
    }

    /**
     * @return User[]
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getUsers(): array
    {
        if (!$this->loaded) {
            $watchers = $this->Jira->issue()->watchers()->list($this->Issue->getKey());

            foreach ($watchers as $UserInfo) {
                $Watcher = User::fromStdClass($this->Jira, $UserInfo, $this->Issue);
                parent::addUsers($Watcher);
            }

            $this->loaded = true;
        }

        return parent::getUsers();
    }

    /**
     * Add user to list of issue's watchers, using user's name (login)
     *
     * @param string ...$names
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function addUsersByName(string ...$names): WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new User($this->Jira, $name);
        }

        return $this->addUsers(...$users);
    }

    /**
     * Add user to list of issue's watchers
     *
     * @param User ...$users
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function addUsers(User ...$users): UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey());
            }
        }

        return UsersList::addUsers(...$users);
    }

    /**
     * Remove user from list of issue's watchers, using user's name (login)
     *
     * @param string ...$names
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function removeUsersByName(string ...$names): WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new User($this->Jira, $name);
        };

        return $this->removeUsers($users);
    }

    /**
     * Remove user from list of issue's watchers
     *
     * @param User ...$users
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function removeUsers(User ...$users): UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            }
        }

        return UsersList::removeUsers(...$users);
    }
}
