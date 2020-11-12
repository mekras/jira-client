<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class SingleSelectField
 *
 * Wrapper class for 'single select' type custom field
 */
abstract class SingleSelectField extends CustomField
{
    /** @var string */
    protected $value;

    /** @return string[] - list of items available for this field. */
    abstract public function getItemsList(): array;

    /**
     * @return string
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue(): string
    {
        if ($this->getOriginalObject() === null) {
            return '';
        }

        return $this->getOriginalObject()->value;
    }

    /**
     * @param string $value
     *
     * @return array
     */
    public static function generateSetter($value): array
    {
        if ($value !== null) {
            $value = ['value' => $value];
        }

        return [['set' => $value]];
    }

    /**
     * @param string $value
     *
     * @return $this
     *
     * @throws \Mekras\Jira\Exception\CustomField
     */
    public function setValue($value)
    {
        if (isset($value) && !in_array($value, $this->getItemsList())) {
            throw new \Mekras\Jira\Exception\CustomField(
                "Can't select '{$value}' item. "
                . "Available items for field '{$this->getName()}' are: '"
                . implode("', '", $this->getItemsList()) . "'\n"
            );
        }

        parent::setValue($value);

        return $this;
    }
}
