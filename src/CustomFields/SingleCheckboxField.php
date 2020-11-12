<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class SingleCheckboxField
 *
 * Wrapper class for 'checkbox' type custom field with single choice
 */
abstract class SingleCheckboxField extends CheckboxField
{
    /**
     * @param bool $checked_state   - true: 'check' item,
     *                              false: 'uncheck' it
     *
     * @return $this
     */
    public function setChecked($checked_state = true)
    {
        $this->checkAll($checked_state);

        return $this;
    }

    public function isChecked(string $checkbox = ''): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return string - name of checked item, if any
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue()
    {
        $checked = parent::getValue();

        return reset($checked) ?: '';
    }
}
