<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

class WatchersList extends \Mekras\Jira\UsersList
{
    protected $initialized = false;
    protected $loaded = false;

    /**
     * @param array $users_info
     * @param \Mekras\Jira\Issue $Issue
     * @param \Mekras\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception
     */
    public static function fromStdClass(array $users_info, \Mekras\Jira\Issue $Issue = null, \Mekras\Jira\REST\Client $Jira = null) : \Mekras\Jira\UsersList
    {
        if (!isset($Issue)) {
            throw new \Mekras\Jira\Exception("Watchers list requires parent Issue object to work properly");
        }

        $users = [];
        foreach ($users_info as $UserInfo) {
            $users[] = \Mekras\Jira\User::fromStdClass($UserInfo, $Issue, $Jira);
        }

        $Instance = new static($users, $Jira);
        $Instance->Issue = $Issue;
        $Instance->initialized = true;

        return $Instance;
    }

    /**
     * @param string $issue_key
     * @param \Mekras\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return WatchersList
     *
     * @throws \Mekras\Jira\Exception
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function forIssue(string $issue_key, \Mekras\Jira\REST\Client $Jira = null) : WatchersList
    {
        $Issue = new \Mekras\Jira\Issue($issue_key, $Jira);
        return $Issue->getWatchers();
    }

    /**
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function clearList() : \Mekras\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($this->getUsers() as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            };
        }

        $this->loaded = false;
        return \Mekras\Jira\UsersList::clearList();
    }

    /**
     * @return \Mekras\Jira\User[]
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getUsers() : array
    {
        if (!$this->loaded) {
            $watchers = $this->Jira->issue()->watchers()->list($this->Issue->getKey());

            foreach ($watchers as $UserInfo) {
                $Watcher = \Mekras\Jira\User::fromStdClass($UserInfo, $this->Issue, $this->Jira);
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
    public function addUsersByName(string ...$names) : WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new \Mekras\Jira\User($name, $this->Jira);
        }
        return $this->addUsers(...$users);
    }

    /**
     * Add user to list of issue's watchers
     *
     * @param \Mekras\Jira\User ...$users
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function addUsers(\Mekras\Jira\User ...$users) : \Mekras\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey());
            }
        }

        return \Mekras\Jira\UsersList::addUsers(...$users);
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
    public function removeUsersByName(string ...$names) : WatchersList
    {
        $users = [];
        foreach ($names as $name) {
            $users[] = new \Mekras\Jira\User($name, $this->Jira);
        }
        ;
        return $this->removeUsers($users);
    }

    /**
     * Remove user from list of issue's watchers
     *
     * @param \Mekras\Jira\User ...$users
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function removeUsers(\Mekras\Jira\User ...$users) : \Mekras\Jira\UsersList
    {
        if ($this->initialized) {
            foreach ($users as $User) {
                $User->watchIssue($this->Issue->getKey(), false);
            }
        }

        return \Mekras\Jira\UsersList::removeUsers(...$users);
    }
}
