<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class User
{
    public const AVATAR_L = '48x48';

    public const AVATAR_M = '32x32';

    public const AVATAR_S = '24x24';

    public const AVATAR_XS = '16x16';

    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var string[] */
    protected $expanded = [];

    /** @var string */
    protected $name;

    /** @var \Mekras\Jira\Group[] */
    protected $groups;

    /** @var Issue */
    protected $Issue;

    /**
     * Initialize User object on data from API
     *
     * @param Client    $jiraClient JIRA API client to use.
     * @param \stdClass $UserInfo   User information received from JIRA API.
     * @param Issue     $Issue      When current user somehow related to an issue: e. g. is
     *                              Assignee or is listed in some custom field.
     *
     * @return static
     *
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $UserInfo,
        Issue $Issue = null
    ): User {
        $Instance = new static($jiraClient, $UserInfo->name ?? $UserInfo->accountId);
        $Instance->Issue = $Issue;
        $Instance->OriginalObject = $UserInfo;

        return $Instance;
    }

    /**
     * Get user from API by ID.
     *
     * This method makes an API request immediately, while
     *     $User = new User(<name>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $User->getDisplayName()).
     *
     * @param Client $jiraClient
     * @param string $userName Name of user in JIRA. Don't mess with display name you see in UI!
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, string $userName): User
    {
        $Instance = new static($jiraClient, $userName);
        $Instance->getOriginalObject();

        return $Instance;
    }

    /**
     * Search for users by login. display name or email.
     * This gives you a result similar to the one you get in 'Uses' administration page of JIRA Web
     * UI.
     *
     * @param string $pattern    User login, display name or email.
     * @param Client $jiraClient JIRA API client to use.
     *
     * @return static[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function search(Client $jiraClient, string $pattern): array
    {
        $users = $jiraClient->user()->search($pattern);

        $result = [];
        foreach ($users as $UserInfo) {
            $User = static::fromStdClass($jiraClient, $UserInfo, null);
            $result[$User->getName()] = $User;
        }

        return $result;
    }

    /**
     * Search for user by exact match in email address
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param string $email      User email.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception - on JIRA API interaction errors
     * @throws \Mekras\Jira\Exception\User - when no user with given email found in JIRA
     */
    public static function byEmail(Client $jiraClient, string $email): User
    {
        $users = $jiraClient->user()->search($email);

        foreach ($users as $UserInfo) {
            if ($UserInfo->emailAddress === $email) {
                return static::fromStdClass($jiraClient, $UserInfo, null);
            }
        }

        throw new \Mekras\Jira\Exception\User(
            "User with email '{$email}' not found in Jira"
        );
    }

    /**
     * User constructor.
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param string $name       Name of user in JIRA. Don't mess with display name you see in UI!
     */
    public function __construct(Client $jiraClient, string $name)
    {
        $this->Jira = $jiraClient;
        $this->name = $name;
    }

    /**
     * @param string[] $expand - ask JIRA to provide additional information in response
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject(array $expand = []): \stdClass
    {
        $new_expand = false;
        foreach ($expand as $item) {
            if (!array_key_exists($item, $this->expanded)) {
                $this->expanded[$item] = null;
                $new_expand = true;
            }
        }

        if (!isset($this->OriginalObject) || $new_expand) {
            $this->OriginalObject = $this->Jira->user()->get(
                $this->getName(),
                array_keys($this->expanded),
                true
            );
            $this->groups = null;
        }

        return $this->OriginalObject;
    }

    public function __toString()
    {
        return "{$this->getDisplayName()} ({$this->getName()})";
    }

    /**
     * Return user picture URL.
     *
     * @param string $size Image size (see AVATAR_* constants).
     *
     * @return string|null
     *
     * @throws REST\Exception
     *
     * @since x.x
     */
    public function getAvatarUrl(string $size = self::AVATAR_M): ?string
    {
        return $this->getOriginalObject()->avatarUrls->{$size} ?? null;
    }

    public function getKey(): string
    {
        return $this->getOriginalObject()->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->getOriginalObject()->displayName;
    }

    public function getEmail(): string
    {
        // Email field can be omitted in some JIRA API responses (e.g. in components)
        if (!isset($this->getOriginalObject()->emailAddress)) {
            $this->OriginalObject = null; // drop cache to force data reload
        }

        return (string) $this->getOriginalObject()->emailAddress;
    }

    public function isActive(): bool
    {
        return $this->getOriginalObject()->active;
    }

    /**
     * Check if user belongs to at least one of listed groups
     *
     * @param string|string[] $group_names
     *
     * @return bool - true when user is member of any of given groups
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function isMemberOf($group_names): bool
    {
        $group_names = (array) $group_names;
        foreach ($this->getGroups() as $Group) {
            if (in_array($Group->getName(), $group_names)) {
                return true;
            }
        }

        return false;
    }

    /**
     * List all groups user is member of
     *
     * @return \Mekras\Jira\Group[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getGroups(): array
    {
        // Groups list can be omitted in some JIRA API responses (e.g. in comments)
        if (!isset($this->groups)) {
            $UserInfo = $this->getOriginalObject(
                ['groups']
            ); // force user data reload to initialize groups list

            $this->groups = [];
            if (isset($UserInfo->groups)) {
                foreach ($UserInfo->groups->items as $GroupInfo) {
                    $Group = Group::fromStdClass($this->Jira, $GroupInfo);
                    $this->groups[$Group->getName()] = $Group;
                }
            }
        }

        return $this->groups;
    }

    /**
     * Start or stop watching issue (add/remove myself from issue's watchers list)
     *
     * @param string $issue_key - key of issue to start watching
     * @param bool   $watch     - should current user watch the issue?
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function watchIssue(string $issue_key, bool $watch = true): User
    {
        if ($watch) {
            $this->Jira->issue()->watchers()->add($issue_key, $this->getName());
        } else {
            $this->Jira->issue()->watchers()->remove($issue_key, $this->getName());
        }

        return $this;
    }

    /**
     * Assign issue to current user.
     * NOTE: action is applied immediately (causes API call)
     *
     * @param string $issue_key
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function assign(string $issue_key): User
    {
        $this->Jira->issue()->assign($issue_key, $this->getName());

        return $this;
    }
}
