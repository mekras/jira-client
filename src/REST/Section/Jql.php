<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Jql extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/jql/autocompletedata-getFieldAutoCompleteForQueryString
     *
     * @param string $field_name      - list possible values for field
     * @param string $field_value     - list only values starting with this text
     * @param string $predicate_name  - see API Web documentation
     * @param string $predicate_value - see API Web documentation
     *
     * @return array
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getFieldSuggestions(
        string $field_name,
        string $field_value = '',
        string $predicate_name = '',
        string $predicate_value = ''
    ) {
        $parameters = [
            'fieldName' => $field_name,
        ];

        if (!empty($field_value)) {
            $parameters['fieldValue'] = $field_value;
        }

        if (!empty($predicate_name)) {
            $parameters['predicateName'] = $predicate_name;
        }

        if (!empty($predicate_value)) {
            $parameters['predicateValue'] = $predicate_value;
        }

        $Response = $this->rawClient->get('jql/autocompletedata/suggestions', $parameters);

        return $Response->results;
    }
}
