<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

use Mekras\Jira\User;

/**
 * Class SingleUserField
 *
 * Wrapper class for 'user picker' type custom field
 */
abstract class SingleUserField extends CustomField
{
    /** @var User */
    protected $User;

    public function dropCache()
    {
        $this->User = null;

        return parent::dropCache();
    }

    /**
     * @return User|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue(): ?User
    {
        if ($this->isEmpty()) {
            return null;
        }

        if (!isset($this->User)) {
            $UserInfo = $this->getOriginalObject();
            $this->User = User::fromStdClass(
                $this->Issue->getJiraClient(),
                $UserInfo,
                $this->Issue
            );
        }

        return $this->User;
    }

    /**
     * @param string|null $user - name of user
     *
     * @return array
     */
    public static function generateSetter($user): array
    {
        if (!isset($user)) {
            return [['set' => null]];
        }

        return [['set' => ['name' => $user]]];
    }

    /**
     * @param $user
     *
     * @return User
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\CustomField
     */
    protected function loadUser($user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        if (is_string($user)) {
            $users = User::search($this->Issue->getJiraClient(), $user);

            if (!empty($users)) {
                return reset($users);
            }
        }

        throw new \Mekras\Jira\Exception\CustomField(
            "User '{$user}' not found in Jira. Can't change '{$this->getName()}' field value."
        );
    }

    /**
     * @param User|string $user
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\CustomField
     */
    public function setValue($user)
    {
        if (!isset($user)) {
            parent::setValue(null);

            return $this;
        }

        $User = $this->loadUser($user);
        parent::setValue($User->getName());

        return $this;
    }
}
