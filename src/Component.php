<?php

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class Component
{
    /** List of possible default assignee types. */
    const ASSIGNEE_TYPE_UNASSIGNED = 'UNASSIGNED';      // Leave issues with this component unassigned

    const ASSIGNEE_TYPE_COMPONENT_LEAD = 'COMPONENT_LEAD';  // Set assignee to component's lead

    const ASSIGNEE_TYPE_PROJECT_LEAD = 'PROJECT_LEAD';    // Set assignee to project lead

    const ASSIGNEE_TYPE_PROJECT_DEFAULT = 'PROJECT_DEFAULT'; // Use project default assignee.

    /** @var Client */
    protected $Jira;

    /** @var Issue */
    protected $Issue;

    /** @var \stdClass with original data from REST API Response. */
    protected $OriginalObject;

    /** @var array */
    protected $cache = [];

    protected $id;            // 10000,

    protected $update = [];

    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $ComponentInfo,
        Issue $Issue = null
    ): Component {
        $Instance = new static($ComponentInfo->id, $jiraClient);

        $Instance->OriginalObject = $ComponentInfo;
        $Instance->Issue = $Issue;

        if (isset($Issue)) {
            // Init cache with project key, because sometimes $ComponentInfo stdClass has not ->project field.
            // For example, when it is located inside Issue->fields->components field.
            // We don't want to go to API for this data.
            $Instance->cache['project_key'] = $Issue->getProject();
        }

        return $Instance;
    }

    /**
     * Get component info from API by ID
     *
     * This method makes an API request immediately, while
     *     $Component = new Component(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $Component->getName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID of component you want to load.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, int $id): Component
    {
        $Instance = new static($id, $jiraClient);
        $Instance->getOriginalObject();

        return $Instance;
    }

    /**
     * Get all components associated with project.
     *
     * @param Client     $jiraClient JIRA API client to use.
     * @param int|string $project    Project key or ID.
     *
     * @return static[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function forProject(Client $jiraClient, $project): array
    {
        $components = $jiraClient->project()->listComponents($project);

        $result = [];
        foreach ($components as $ComponentInfo) {
            $component = static::fromStdClass($jiraClient, $ComponentInfo, null);
            $result[$component->getID()] = $component;
        }

        return $result;
    }

    /**
     * Search for component in a project by name instead of getting it directly by ID.
     *
     * @param Client     $jiraClient    JIRA API client to use.
     * @param string|int $project       Project key or ID.
     * @param string     $componentName Name of component you want to get.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception - on JIRA API interaction errors
     * @throws \Mekras\Jira\Exception\Component - when no component with such name found in given
     *                                         project.
     */
    public static function byName(
        Client $jiraClient,
        $project,
        string $componentName
    ): Component {
        $components = $jiraClient->project()->listComponents($project);

        foreach ($components as $Component) {
            if ($Component->name === $componentName) {
                return static::fromStdClass($jiraClient, $Component, null);
            }
        }

        throw new \Mekras\Jira\Exception\Component(
            "Component with name '$componentName' not found in project '$project'"
        );
    }

    /**
     * @param Client     $jiraClient    JIRA API client to use.
     * @param string|int $project       Project key or ID.
     * @param string     $componentName Name of component to check.
     *
     * @return bool
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function exists(
        Client $jiraClient,
        $project,
        string $componentName
    ): bool {
        $components = $jiraClient->project()->listComponents($project);

        foreach ($components as $Component) {
            if ($Component->name === $componentName) {
                return true;
            }
        }

        return false;
    }

    public function __construct(int $id, Client $jiraClient)
    {
        $this->id = $id;
        $this->Jira = $jiraClient;
    }

    /**
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject(): \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->component()->get($this->id);
        }

        return $this->OriginalObject;
    }

    protected function dropCache(): void
    {
        $this->OriginalObject = null;
        $this->cache = [];
    }

    public function getProjectID(): int
    {
        $key = 'project_id';
        if (!array_key_exists($key, $this->cache)) {
            $project_id = $this->getOriginalObject()->projectId ?? null;
            if (!isset($project_id)) {
                // we have Component info from Issue->fields->components, and we have to reload it :(
                $this->dropCache();
                $project_id = $this->getOriginalObject()->projectId;
            }

            $this->cache[$key] = $project_id;
        }

        return $this->cache[$key];
    }

    public function getProjectKey(): string
    {
        $key = 'project_key';
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->getOriginalObject()->project;
        }

        return $this->cache[$key];
    }

    /**
     * @param int|string $project - project ID or key (e.g. 10101 or 'DD')
     *
     * @return $this
     */
    public function setProject($project): Component
    {
        if (is_numeric($project)) {
            $this->update['projectId'] = (int) $project;
        } else {
            $this->update['project'] = $project;
        }

        return $this;
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return (string) $this->getOriginalObject()->name;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name): Component
    {
        $this->update['name'] = (string) $name;

        return $this;
    }

    public function getDescription(): string
    {
        return (string) $this->getOriginalObject()->description;
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description): Component
    {
        $this->update['description'] = (string) $description;

        return $this;
    }

    public function getLead(): ?User
    {
        $key = 'lead';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = null;

            $UserInfo = $this->getOriginalObject()->lead ?? null;
            if (isset($UserInfo)) {
                $this->cache[$key] = User::fromStdClass($this->Jira, $UserInfo, null);
            }
        }

        return $this->cache[$key];
    }

    /**
     * @param User|string $User - user name (e.g. testuser) or object.
     *
     * @return $this
     *
     * @throws \Mekras\Jira\Exception\Component
     */
    public function setLead($User): Component
    {
        if (is_string($User)) {
            try {
                $User = User::get($this->Jira, $User);
            } catch (\Mekras\Jira\Exception $e) {
                throw new \Mekras\Jira\Exception\Component(
                    "Can't change component's lead: user not found in Jira.", 0, $e
                );
            }
        }

        $this->update['leadUserName'] = $User->getName();

        return $this;
    }

    /**
     * @return string
     */
    public function getAssigneeType(): string
    {
        return $this->getOriginalObject()->assigneeType ?? self::ASSIGNEE_TYPE_UNASSIGNED;
    }

    /**
     * @param string $assignee_type
     *
     * @return $this
     */
    public function setAssigneeType($assignee_type): Component
    {
        $this->update['assigneeType'] = (string) $assignee_type;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getDefaultAssignee(): ?User
    {
        $key = 'assignee';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = null;

            $UserInfo = $this->getOriginalObject()->assignee ?? null;
            if (isset($UserInfo)) {
                $this->cache[$key] = User::fromStdClass($this->Jira, $UserInfo, null);
            }
        }

        return $this->cache[$key];
    }

    public function save(): Component
    {
        if ($this->id !== 0) {
            $ComponentInfo = $this->Jira->component()->update(
                $this->getID(),
                $this->update
            );
        } else {
            $ComponentInfo = $this->Jira->component()->create(
                $this->getProjectKey(),
                $this->getName(),
                $this->update
            );
        }

        $this->dropCache();
        $this->OriginalObject = $ComponentInfo;

        return $this;
    }
}
