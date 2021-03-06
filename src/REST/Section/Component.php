<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Component extends Section
{
    /**
     * Create new component with name <name> in project <project>
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-createComponent
     *
     * @param string|int $project         - parent project ID or key (e.g. 10000 or 'EX')
     * @param string     $name            - component name
     * @param array      $optional_fields - additional fields to set for component
     *
     * @return \stdClass
     * @see Component::get() DocBlock for more info about format
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function create(string $project, string $name, array $optional_fields = []): \stdClass
    {
        $args = $optional_fields;

        if (is_numeric($project)) {
            $args['projectId'] = (int) $project;
        } else {
            $args['project'] = $project;
        }

        $args['name'] = $name;

        return $this->rawClient->post("component", $args);
    }

    /**
     * Delete an existing Component
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-delete
     *
     * @param int $id             - unique Component ID
     * @param int $move_issues_to - apply this component to all issues, who had the deleted one
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function delete(int $id, int $move_issues_to = 0): void
    {
        $args = [];

        if ($move_issues_to === 0) {
            $args['moveIssuesTo'] = $move_issues_to;
        }

        $this->rawClient->delete("component/{$id}", $args);
    }

    /**
     * Get single JIRA Component data by ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-getComponent
     *
     * @param int $id - unique Component ID
     *
     * @return \stdClass - component info (some of data is not shown)
     *                       [
     *                         'project'      => <component's project key (e.g. 'EX'), string>,
     *                         'projectId'    => <component's project ID, int>,
     *                         'id'           => <unique Component ID, int>,
     *                         'name'         => <texual component name, string>,
     *                         'description'  => <detailed component description, string>,
     *                         'lead'         => <Jira user info, \stdClass>,
     *                         'assignee'     => <Jira user info, \stdClass>,
     *                         'assigneeType' => <one of supported assignee types (e.g.
     *                         PROJECT_LEAD), string>
     *                         ...
     *                       ]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id): \stdClass
    {
        return $this->rawClient->get("component/{$id}");
    }

    /**
     * Update an existing component
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-updateComponent
     * @see Component::create() method DocBlock for more info about parameters and returned data.
     *
     * @param int   $id                     - unique Component ID
     * @param array $update                 - fields to update
     *                                      e.g.:
     *                                      {
     *                                      'name': "Component 1",
     *                                      'description': "This is a JIRA component",
     *                                      'leadUserName': "fred",
     *                                      'assigneeType': "PROJECT_LEAD",
     *                                      'isAssigneeTypeValid': false,
     *                                      'project': "PROJECTKEY",
     *                                      'projectId': 10000
     *                                      }
     *
     * @return \stdClass - updated component info
     * @see Component::get() DocBlock for more info about format
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function update(
        int $id,
        array $update = []
    ): \stdClass {
        return $this->rawClient->put("component/{$id}", $update);
    }
}
