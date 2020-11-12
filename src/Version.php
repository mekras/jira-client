<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\REST\Client;

class Version
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    protected $cache = [];

    /** @var int */
    protected $id;              // 10000

    protected $update = [];

    /** @var Issue */
    protected $Issue;

    /**
     * Initialize Version object on data obtained from API
     *
     * @param Client    $jiraClient  JIRA API client to use.
     * @param \stdClass $VersionInfo Version information received from JIRA API.
     * @param Issue     $Issue       Issue, the version is attached to.
     *
     * @return static
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $VersionInfo,
        Issue $Issue = null
    ): Version {
        $Instance = new static((int) $VersionInfo->id, $jiraClient);

        $Instance->OriginalObject = $VersionInfo;
        $Instance->Issue = $Issue;

        return $Instance;
    }

    /**
     * Get version info by ID.
     *
     * This method makes an API request immediately, while
     *     $Version = new Version(<id>, <Client>);
     * requests JIRA only when you really need the data (e.g. the first time you call
     * $User->getDisplayName()).
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param int    $id         ID.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function get(Client $jiraClient, int $id): Version
    {
        $Instance = new static($id, $jiraClient);
        $Instance->getOriginalObject();

        return $Instance;
    }

    /**
     * List all versions in given project
     *
     * @param Client     $jiraClient JIRA API client to use.
     * @param string|int $project    Project name or ID.
     *
     * @return static[] - list of versions indexed by IDs
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function forProject(Client $jiraClient, $project): array
    {
        $versions = $jiraClient->project()->listVersions($project);

        $result = [];
        foreach ($versions as $VersionInfo) {
            $Version = static::fromStdClass($jiraClient, $VersionInfo, null);
            $result[$Version->getId()] = $Version;
        }

        return $result;
    }

    /**
     * Look for version with specific name insire a project
     *
     * @param Client     $jiraClient  JIRA API client to use.
     * @param string|int $project     Project name or ID.
     * @param string     $versionName Name of version to look for.
     *
     * @return static
     *
     * @throws \Mekras\Jira\REST\Exception - on JIRA API interaction errors
     * @throws \Mekras\Jira\Exception\Version - when no version with given name found in project
     */
    public static function byName(Client $jiraClient, $project, string $versionName): Version
    {
        $versions = $jiraClient->project()->listVersions($project);

        foreach ($versions as $VersionInfo) {
            if ($VersionInfo->name === $versionName) {
                return static::fromStdClass($jiraClient, $VersionInfo, null);
            }
        }

        throw new \Mekras\Jira\Exception\Version(
            "Version with name '{$versionName}' not found in project '{$project}'"
        );
    }

    /**
     * Check if version with specific name exists in a project
     *
     * @param Client     $jiraClient  JIRA API client to use.
     * @param string|int $project     Project name or ID.
     * @param string     $versionName Name of version to look for.
     *
     * @return bool - true when version exists
     *
     * @throws \Mekras\Jira\REST\Exception - on JIRA API interaction errors
     */
    public static function exists(Client $jiraClient, $project, $versionName): bool
    {
        $versions = $jiraClient->project()->listVersions($project);

        foreach ($versions as $VersionInfo) {
            if ($VersionInfo->name === $versionName) {
                return true;
            }
        }

        return false;
    }

    public function __construct(int $id, Client $Jira)
    {
        $this->id = $id;
        $this->Jira = $Jira;
    }

    /**
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    protected function getOriginalObject()
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->version()->get($this->getId());
        }

        return $this->OriginalObject;
    }

    protected function resetCache()
    {
        $this->OriginalObject = null;
        $this->cache = [];
    }

    public function getID(): int
    {
        return $this->id;
    }

    public function getProjectID(): int
    {
        return (int) $this->getOriginalObject()->projectId;
    }

    public function getProjectKey(): string
    {
        $key = 'project_key';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = '';

            $ProjectInfo = $this->Jira->project()->get($this->getProjectId());
            $this->cache[$key] = $ProjectInfo->key;
        }

        return $this->cache[$key];
    }

    public function setProject($project): Version
    {
        if (is_numeric($project)) {
            $this->update["projectId"] = (int) $project;
        } else {
            $this->update["project"] = $project;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->getOriginalObject()->name;
    }

    public function setName(string $name): Version
    {
        $this->update['name'] = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->getOriginalObject()->description ?? '';
    }

    public function setDescription(string $description): Version
    {
        $this->update['description'] = $description;

        return $this;
    }

    public function getStartDate(): int
    {
        $key = 'start_date';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = 0;

            $start_date = $this->getOriginalObject()->startDate ?? null;
            if (isset($start_date)) {
                $this->cache[$key] = strtotime($start_date);
            }
        }

        return $this->cache[$key];
    }

    public function setStartDate(int $timestamp): Version
    {
        if ($timestamp === 0) {
            $this->update['startDate'] = '';
        }

        $this->update['startDate'] = date('Y-m-d', $timestamp);

        return $this;
    }

    public function getReleaseDate(): int
    {
        $key = 'release_date';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = 0;

            $release_date = $this->getOriginalObject()->releaseDate ?? null;
            if (isset($release_date)) {
                $this->cache[$key] = strtotime($release_date);
            }
        }

        return $this->cache[$key];
    }

    public function setReleaseDate(int $timestamp): Version
    {
        if ($timestamp === 0) {
            $this->update['releaseDate'] = '';
        }

        $this->update['releaseDate'] = date('Y-m-d', $timestamp);

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->getOriginalObject()->archived ?? false;
    }

    public function setArchived(bool $archived = true): Version
    {
        $this->update['archived'] = $archived;

        return $this;
    }

    public function isReleased(): bool
    {
        return $this->getOriginalObject()->released ?? false;
    }

    public function setReleased(bool $released = true): Version
    {
        $this->update['released'] = $released;

        return $this;
    }

    public function isOverdue(): bool
    {
        return $this->getOriginalObject()->overdue ?? false;
    }

    public function save()
    {
        $VersionInfo = null;

        if ($this->id !== 0) {
            if (empty($this->update)) {
                return $this;
            }

            $VersionInfo = $this->Jira->version()->update(
                $this->getId(),
                $this->update
            );
        } else {
            $project = $this->update['projectId'] ?? $this->update['project'] ?? null;
            $name = $this->update['name'] ?? null;

            if (!isset($project) || !isset($name)) {
                throw new \Mekras\Jira\Exception\Version(
                    'JIRA project and version name are required for new version creation'
                );
            }

            $VersionInfo = $this->Jira->version()->create(
                $project,
                $name,
                $this->update
            );

            $this->id = $VersionInfo->id; // we created a version in JIRA. Now we know its ID
        }

        $this->update = [];
        $this->resetCache();
        $this->OriginalObject = $VersionInfo;

        return $this;
    }

    /**
     * Delete version
     *
     * @param string|null $move_fixed_to
     * @param string|null $move_affected_to
     *
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Version::delete for parameters meaning description
     *
     */
    public function delete(string $move_fixed_to = null, string $move_affected_to = null)
    {
        if ($this->getID() === 0) {
            return;
        }

        $this->Jira->version()->delete($this->getID(), $move_fixed_to, $move_affected_to);
    }

    /**
     * Check that current version was not released yet and release it (mark as 'released' and set
     * release date to now). Have no effect on already released versions.
     */
    public function release(): Version
    {
        if ($this->isReleased()) {
            return $this;
        }

        $this->setReleased()
            ->setReleaseDate(time())
            ->save();

        return $this;
    }
}
