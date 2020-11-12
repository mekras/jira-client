<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class SingleUserField
 *
 * Wrapper class for 'user picker' type custom field
 */
abstract class SingleUserField extends CustomField
{
    /** @var \Mekras\Jira\User */
    protected $User;

    public function dropCache()
    {
        $this->User = null;

        return parent::dropCache();
    }

    /**
     * @return \Mekras\Jira\User|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue(): ?\Mekras\Jira\User
    {
        if ($this->isEmpty()) {
            return null;
        }

        if (!isset($this->User)) {
            $UserInfo = $this->getOriginalObject();
            $this->User = \Mekras\Jira\User::fromStdClass(
                $UserInfo,
                $this->Issue,
                $this->Issue->getJira()
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
     * @return \Mekras\Jira\User
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\CustomField
     */
    protected function loadUser($user): \Mekras\Jira\User
    {
        if ($user instanceof \Mekras\Jira\User) {
            return $user;
        }

        if (is_string($user)) {
            $users = \Mekras\Jira\User::search($user, $this->Issue->getJira());

            if (!empty($users)) {
                return reset($users);
            }
        }

        throw new \Mekras\Jira\Exception\CustomField(
            "User '{$user}' not found in Jira. Can't change '{$this->getName()}' field value."
        );
    }

    /**
     * @param \Mekras\Jira\User|string $user
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
