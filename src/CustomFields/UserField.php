<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class UserField
 *
 * Wrapper class for 'multi user picker' type custom field
 */
abstract class UserField extends CustomField
{
    /** @var \Mekras\Jira\User[] - users listed in field */
    protected $value;

    /** @var \Mekras\Jira\User[] - new field state to be set on ->save() call: [ <user 1 name> => true, <user 2 name> => true ] */
    protected $update;

    public function dropCache()
    {
        $this->value = null;
        $this->update = null;

        return parent::dropCache();
    }

    /**
     * @return \Mekras\Jira\User[] - list of users listed in field
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getSelectedUsers()
    {
        if (!isset($this->value)) {
            $this->value = [];

            $Field = $this->getOriginalObject();

            foreach ((array) $Field as $UserData) {
                $User = \Mekras\Jira\User::fromStdClass(
                    $UserData,
                    $this->Issue,
                    $this->Issue->getJira()
                );
                $this->value[$User->getName()] = $User;
            }
        }

        return $this->value;
    }

    /**
     * Search user in JIRA.
     *
     * @param \Mekras\Jira\User|string $user
     *
     * @return \Mekras\Jira\User|null
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
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
            "User '{$user}' not found in Jira. Can't add it to '{$this->getName()}' field."
        );
    }

    /**
     * Get current list of users selected in a field value
     *
     * @return \Mekras\Jira\User[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue(): array
    {
        return $this->getSelectedUsers();
    }

    /**
     * @param string[] $value - list of user names to lists in field
     *
     * @return array
     */
    public static function generateSetter($value): array
    {
        $users_to_select = [];
        foreach ($value as $item_name) {
            $users_to_select[] = ['name' => $item_name];
        }

        return [['set' => $users_to_select]];
    }

    /**
     * Set field value to exact list of users
     *
     * @param \Mekras\Jira\User[]|string[] $value - list of names of selected users, or User
     *                                            objects, or emails JIRA can use to find users.
     *                                            You can mix the values.
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
     */
    public function setValue($value)
    {
        $this->update = [];
        foreach ($value as $user) {
            $User = $this->loadUser($user);
            $this->update[$User->getName()] = true;
        }

        $update = static::generateSetter(array_keys($this->update));
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * Clear field value. Equivalent to ->setValue([])
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
     */
    public function clear()
    {
        return $this->setValue([]);
    }

    /**
     * Add user to field value
     *
     * @param \Mekras\Jira\User|string $user - username, name or e-mail address as string or User
     *                                       object
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
     */
    public function addUser($user)
    {
        if (!isset($this->update)) {
            $this->update = $this->getSelectedUsers();
        }

        $User = $this->loadUser($user);
        $this->update[$User->getName()] = $User;

        $update = static::generateSetter(array_keys($this->update));
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * Remove user from field value
     *
     * @param \Mekras\Jira\User|string $user
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
     */
    public function removeUser($user)
    {
        if (!isset($this->update)) {
            $this->update = $this->getSelectedUsers();
        }

        if (is_string($user) && isset($this->update[$user])) {
            unset($this->update[$user]);
        } else {
            $User = $this->loadUser($user);
            unset($this->update[$User->getName()]);
        }

        $update = static::generateSetter(array_keys($this->update));
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * Check if user is already selected in field
     *
     * @param \Mekras\Jira\User|string $user
     *
     * @return bool - true when user is listed in current field value
     *
     * @throws \Mekras\Jira\REST\Exception on JIRA API interaction error
     * @throws \Mekras\Jira\Exception\CustomField when user does not exist in JIRA
     */
    public function hasUser($user)
    {
        $selected_users = $this->getSelectedUsers();
        if (is_string($user) && isset($selected_users[$user])) {
            return true;
        }

        $User = $this->loadUser($user);

        return isset($selected_users[$User->getName()]);
    }
}
