<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\CustomFields;

/**
 * Class TextField
 *
 * Wrapper class for 'text' type custom field
 */
abstract class TextField extends CustomField
{
    /**
     * @return string
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getValue()
    {
        return (string) $this->getOriginalObject();
    }

    /**
     * @param string $value
     *
     * @return array
     */
    public static function generateSetter($value): array
    {
        if ($value !== null) {
            $value = (string) $value;
        }

        return [['set' => $value]];
    }
}
