<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class NumberField
 *
 * Wrapper class for 'number' type custom field
 */
abstract class NumberField extends CustomField
{
    /**
     * @return float
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue(): float
    {
        return (float) $this->getOriginalObject();
    }

    /**
     * @param float|null $value
     *
     * @return array
     */
    public static function generateSetter($value): array
    {
        if ($value !== null) {
            $value = (float) $value;
        }

        return [['set' => $value]];
    }
}
