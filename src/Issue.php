<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira;

use Mekras\Jira\CustomFields\CustomField;
use Mekras\Jira\Issue\Priority;
use Mekras\Jira\Issue\Resolution;
use Mekras\Jira\Issue\Status;
use Mekras\Jira\REST\Client;

/**
 * Issue.
 *
 * Represents particular Jira issue with plenty of wrappers to interact with common and custom Jira
 * fields.
 */
class Issue
{
    /**
     * Cached issue information from Jira: REST API response 'as is'.
     *
     * @var \stdClass
     */
    private $baseIssue;

    /**
     * Cached issue data.
     *
     * Various objects of various types: 'History' issue info, 'Assignee' field, etc.
     * Everything, which is not classified as custom fields.
     *
     * @var array<mixed>
     */
    private $cachedData = [];

    /**
     * Cached objects for issue's custom fields.
     *
     * @var CustomField[]
     */
    private $customFields = [];

    /**
     * List of expands for issue.
     *
     * REST API request with 'expand' parameter makes API to add additional information about issue
     * to response, like issue history, rendered fields and so on.
     *
     * @var string[]
     */
    private $expands = [];

    /**
     * @var Client
     */
    private $jiraClient;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $project;

    /**
     * List of field updates to be sent to JIRA API on ->save() call.
     *
     * @var array
     * @see \Mekras\Jira\REST\Section\Issue::edit for more info on structure of data stored here
     *      ($update parameter)
     */
    private $updateFields = [];

    /**
     * Load info for issue identified by key from Jira using REST API
     *
     * @param Client   $jiraClient JIRA API client to use.
     * @param string   $issueKey
     * @param string[] $fields     Request only fields listed.
     * @param string[] $expand     Provide additional info for issue.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Issue::get()
     *
     */
    public static function byKey(
        Client $jiraClient,
        string $issueKey,
        array $fields = [],
        array $expand = []
    ): Issue {
        $IssueInfo = $jiraClient->issue()->get($issueKey, $fields, $expand);

        return static::fromStdClass($jiraClient, $IssueInfo, $fields, $expand);
    }

    /**
     * Load list of issues by their keys from Jira REST API
     *
     * @param Client   $jiraClient JIRA API client to use.
     * @param string[] $issueKeys  List of issue keys for issues load.
     * @param string[] $fields     Load only listed field values.
     * @param string[] $expand     Provide additional info for each issue.
     *
     * @return static[]
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Issue::search()
     *
     */
    public static function byKeys(
        Client $jiraClient,
        array $issueKeys,
        array $fields = [],
        array $expand = []
    ): array {
        if (empty($issueKeys)) {
            return [];
        }

        $query = "key IN (" . implode(',', $issueKeys) . ")";

        return static::search($jiraClient, $query, $fields, $expand, 1000, 0);
    }

    /**
     * Perform brief check of given \stdClass object for correctness and initialize
     * \Mekras\Jira\Issue object on it.
     *
     * @param Client    $jiraClient JIRA API client to use.
     * @param \stdClass $BaseIssue
     * @param string[]  $fields     <BaseIssue> object was loaded only with this fields.
     * @param string[]  $expand     <BaseIssue> object was loaded with this info expanded.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Issue
     */
    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $BaseIssue,
        array $fields = [],
        array $expand = []
    ): Issue {
        if (empty($BaseIssue->key)) {
            throw new \Mekras\Jira\Exception\Issue(
                'Provided data does not contain attribute "key"'
            );
        }

        if (!empty($fields) && !empty($expand)) {
            throw new \Mekras\Jira\Exception\Issue(
                'Issue object does not support partial fields load in combination with non-empty \'expand\' parameter.' .
                ' Use any of them, but no both to make Issue do optimal requests to JIRA API.' .
                ' More info is in documentation at https://github.com/badoo/jira-client.git'
            );
        }

        static $hack_fields = [
            'key' => false, // field is not in ->fields, but we don't want to cache it anyway.
            'id' => true, // field is not in ->fields, cache with hack
            'self' => true,
        ];

        $Issue = new static($jiraClient, $BaseIssue->key);

        foreach ($hack_fields as $field_id => $cache) {
            if (!$cache) {
                continue;
            }

            // this info on issue is not inside 'fields' subtree of Issue \stdClass object,
            // we have to cache it with hack here.
            if (isset($BaseIssue->{$field_id})) {
                $Issue->cacheData($field_id, $BaseIssue->{$field_id});
            }
        }

        if (empty($fields)) {
            // We save BaseIssue object only when it contains full information on issue fields.
            // This is done to prevent code from requesting API each time we want to get empty issue field.
            // This also preventes from returning 'null' about field that is not empty but we just don't have info on it.
            $Issue->baseIssue = $BaseIssue;
            $Issue->expands = $expand;
        } else {
            // We can't store BaseIssue object because we know it does not contain the full fields information.
            // Store all loaded fields in cache to use their values when we need and to not break other field getters.
            foreach ($fields as $field_id) {
                if (array_key_exists($field_id, $hack_fields)) {
                    continue; // we already cached a field
                }

                $Issue->cacheData($field_id, $BaseIssue->fields->{$field_id} ?? null);
            }
        }

        return $Issue;
    }

    /**
     * Load list of issues using search query in Jira Query Language.
     *
     * @param Client   $jiraClient JIRA API client to use.
     * @param string   $jql        Search query string.
     * @param string[] $fields     Load only listed field values.
     * @param string[] $expand     Load additional issues info.
     * @param int      $limit      Return at most <limit> issues.
     * @param int      $offset     Skip first <offset> issues found.
     *
     * @return static[] - list of issues are fit the search request.
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Issue::search()
     */
    public static function search(
        Client $jiraClient,
        string $jql,
        array $fields = [],
        array $expand = [],
        int $limit = 1000,
        int $offset = 0
    ): array {
        $issuesList = $jiraClient->issue()->search($jql, $fields, $expand, $limit, $offset);

        $issueObjects = [];

        foreach ($issuesList as $issueInfo) {
            $Issue = static::fromStdClass($jiraClient, $issueInfo, $fields, $expand);
            $Issue->expands = $expand;
            $issueObjects[$Issue->getKey()] = $Issue;
        }

        return $issueObjects;
    }

    /**
     * Issue constructor.
     *
     * WARNING: new Issue(<key>) can be used in many places to return lists of issues.
     *          This means \Mekras\Jira\Issue class constructor should be 'light' and should not
     *          perform full initialization with REST API requests within to not cause performance
     *          degradation.
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param string $issueKey
     *
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function __construct(Client $jiraClient, string $issueKey)
    {
        $issueKey = trim($issueKey);
        if ($issueKey === '') {
            throw new \Mekras\Jira\Exception\Issue(
                "Can't create Issue object with empty issue key"
            );
        }

        $this->jiraClient = $jiraClient;
        $this->key = $issueKey;
    }

    public function __toString(): string
    {
        return "[{$this->getKey()}]: {$this->getSummary()}";
    }

    public function addComment(
        string $text,
        ?array $visibility = [],
        bool $expand_rendered = false
    ): \Mekras\Jira\Issue\Comment {
        $CommentInfo = $this->jiraClient->issue()->comment()->create(
            $this->getKey(),
            $text,
            $visibility,
            $expand_rendered
        );
        $this->dropCache();

        $Comment = \Mekras\Jira\Issue\Comment::fromStdClass($CommentInfo, $this);

        return $Comment;
    }

    /**
     * Add one or several components to issue
     *
     * @param string|int ...$components - names or IDs of components to add to issue
     *
     * @return $this
     */
    public function addComponents(...$components): Issue
    {
        $components_update = $this->updateFields['components'] ?? [];

        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int) $component;
                $components_update[] = ['add' => ['id' => "$component"]];
            } else {
                $components_update[] = ['add' => ['name' => $component]];
            }
        }

        return $this->edit('components', $components_update);
    }

    /**
     * Add labels to issue, keeping all labels it already has
     *
     * @param string ...$labels
     *
     * @return $this
     */
    public function addLabels(string ...$labels): Issue
    {
        $to_add = array_values(array_unique($labels));

        $update_labels = $this->updateFields['labels'] ?? [];
        foreach ($to_add as $label) {
            $update_labels[] = ['add' => $label];
        }

        return $this->edit(
            'labels',
            $update_labels
        );
    }

    /**
     * Associate version with issue (add it to fixVersions field).
     * Appends a version to issue's versions list, returns an object for added version.
     *
     * @param string $version_name
     * @param bool   $create - create new version in Jira, if it does not already exist.
     *
     * @return Version
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Version
     */
    public function addVersion($version_name, $create = false)
    {
        $versions_to_set = [];
        foreach ($this->getVersions() as $AlreadyAdded) {
            if ($version_name === $AlreadyAdded->getName()) {
                return $AlreadyAdded;
            }

            $versions_to_set[] = ['id' => "{$AlreadyAdded->getID()}"];
        }

        // Create a new version in Jira project if it is needed.
        if ($create && !Version::exists(
                $this->getJiraClient(),
                $this->getProject(),
                $version_name
            )) {
            $VersionToAdd = new Version(0, $this->getJiraClient());
            $VersionToAdd->setProject($this->getProject())->setName($version_name)->save();
        } else {
            $VersionToAdd = Version::byName(
                $this->getJiraClient(),
                $this->getProject(),
                $version_name
            );
        }

        // Update list of versions for issue
        $versions_to_set[] = ['id' => "{$VersionToAdd->getID()}"];
        $this->edit(
            "fixVersions",
            [
                ['set' => $versions_to_set],
            ]
        );
        $this->save();

        return $VersionToAdd;
    }

    public function attachFile(
        string $file_path,
        ?string $file_name = null,
        ?string $file_type = null
    ): \Mekras\Jira\Issue\File {
        return $this->getAttachments()->attach($file_path, $file_name, $file_type);
    }

    /**
     * Delete issue from Jira.
     * DANGER: This action is applied immediately and can't be undone. Be careful.
     */
    public function delete(): void
    {
        $this->jiraClient->issue()->delete($this->getKey());
    }

    /**
     * Edit issue fields
     * NOTE: the changes are actually applied only after ->save() call.
     *
     * @param string $field_id  - ID of field to update. E.g. 'summary' or 'customfield_12345'
     * @param array  $update    - update request
     *                          Example: [ [ 'set' => [ 'id' => 1234' ] ]
     *
     * @return $this
     * @see \Mekras\Jira\REST\Section\Issue::edit DocBlock for more info
     *
     */
    public function edit(string $field_id, array $update): \Mekras\Jira\Issue
    {
        $this->updateFields[$field_id] = $update;

        return $this;
    }

    /**
     * Get issue's assignee user information. 'null' is returned for unassigned issues.
     *
     * @return User|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getAssignee(): ?User
    {
        $Assignee = $this->getCachedData('Assignee');

        if (!isset($Assignee) && isset($this->getBaseIssue()->fields->assignee)) {
            $Assignee = User::fromStdClass(
                $this->jiraClient,
                $this->getFieldValue('assignee'),
                $this
            );
            $this->cacheData('Assignee', $Assignee);
        }

        return $Assignee;
    }

    public function getAttachments(): \Mekras\Jira\Issue\Attachments
    {
        $cache_key = 'Attachments';
        $Attachments = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $attachments_list = $this->getFieldValue('attachment') ?? [];
            $Attachments = \Mekras\Jira\Issue\Attachments::fromStdClass($attachments_list, $this);

            $this->cacheData($cache_key, $Attachments);
        }

        return $Attachments;
    }

    /**
     * Get comment by ID.
     * NOTE: This method does not call API at all, it just creates you an object.
     *       You will not realize comment does not exist until you try to get some field for it.
     *
     * @param int $id
     *
     * @return \Mekras\Jira\Issue\Comment
     */
    public function getComment(int $id): \Mekras\Jira\Issue\Comment
    {
        return new \Mekras\Jira\Issue\Comment($id, $this);
    }

    /**
     * Get list of issue comments
     *
     * @param bool $reload_cache - request API for fresh issue comments ignoring internal class
     *                           cache
     *
     * @return \Mekras\Jira\Issue\Comment[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getComments(bool $reload_cache = false): array
    {
        $cache_key = 'Comments';

        $comments = $this->getCachedData($cache_key);
        if (!isset($comments) || $reload_cache) {
            $comments_list = $this->getFieldValue('comment')->comments;

            $comments = [];
            foreach ($comments_list as $CommentInfo) {
                $Comment = \Mekras\Jira\Issue\Comment::fromStdClass($CommentInfo, $this);
                $comments[$Comment->getID()] = $Comment;
            }

            $this->cacheData($cache_key, $comments);
        }

        return $comments;
    }

    /**
     * Get list of issue components, indexed by IDs
     *
     * @return Component[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getComponents()
    {
        $components = $this->getCachedData('Components');

        if (!isset($components)) {
            $components = [];

            foreach ((array) $this->getFieldValue('components') as $ComponentInfo) {
                $Component = Component::fromStdClass($this->jiraClient, $ComponentInfo, $this);
                $components[$Component->getID()] = $Component;
            }

            $this->cacheData('Components', $components);
        }

        return $components;
    }

    /**
     * @return int - issue creation time unix timestamp
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function getCreatedDate(): int
    {
        return $this->getDateField('created');
    }

    /**
     * @param string $field_class - class name of CustomField to get
     *
     * @return CustomField
     */
    public function getCustomField($field_class)
    {
        /** @var CustomField $CustomField */
        $CustomField = $this->customFields[$field_class] ?? null;

        if (!isset($CustomField)) {
            $CustomField = new $field_class($this);
            $this->customFields[$field_class] = $CustomField;
        }

        return $CustomField;
    }

    /**
     * Get date/time field value as UNIX timestamp. strtotime() is used to parse the value of the
     * field.
     *
     * @param string $field_id - ID of field to parse, e.g. 'created', 'updated' and so on
     * @param array  $expand
     *
     * @return int
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function getDateField(string $field_id, array $expand = []): int
    {
        $time = $this->getFieldValue($field_id, $expand);

        if (empty($time)) {
            return 0;
        }

        if (is_string($time)) {
            $ts = strtotime($time);

            if ($ts === false) {
                throw new \Mekras\Jira\Exception\Issue(
                    "Can't parse '{$field_id}' field value as time string, strtotime('{$time}') returned 'false'"
                );
            }

            $time = $ts;
            $this->cacheData($field_id, $ts);
        }

        return $time;
    }

    /**
     * @return string
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getDescription(): string
    {
        return (string) $this->getFieldValue('description');
    }

    /**
     * @return int
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function getDueDate(): int
    {
        return $this->getDateField('duedate');
    }

    /**
     * @return array
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getEditMeta(): array
    {
        return $this->jiraClient->issue()->getEditMeta($this->getKey());
    }

    /**
     * Get value of custom or system field, checking cache first
     *
     * IMPORTANT: cache is used for field-limited initialization, when issue was loaded from API
     * with only selected fields data instead of default 'all fields info'. Don't bypass it unless
     * you know what you do.
     *
     * @param string   $field_id - custom field id (e.g. 'customfield_12345' or 'summary')
     * @param string[] $expand   - load extra information for issue before getting field value
     *
     * @return mixed
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getFieldValue(string $field_id, array $expand = [])
    {
        if ($this->isCached($field_id)) {
            return $this->getCachedData($field_id);
        }

        return $this->getBaseIssue($expand)->fields->{$field_id} ?? null;
    }

    public function getHistory(): \Mekras\Jira\Issue\History
    {
        $cache_key = 'History';
        $History = $this->getCachedData($cache_key);
        if (!isset($History)) {
            $records = $this->getBaseIssue(
                [\Mekras\Jira\REST\Section\Issue::EXP_CHANGELOG]
            )->changelog->histories;
            $History = \Mekras\Jira\Issue\History::fromStdClass($records, $this);
            $this->cacheData($cache_key, $History);
        }

        return $History;
    }

    /**
     * @return int
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getId(): int
    {
        if ($this->isCached('id')) {
            return $this->getCachedData('id');
        }

        $id = (int) $this->getBaseIssue()->id;
        $this->cacheData('id', $id);

        return $id;
    }

    public function getJiraClient(): Client
    {
        return $this->jiraClient;
    }

    /**
     * Issue key is not changed automatically once object is initialized for the first time.
     * This allows you to 'trust' issue key and use it in arrays as index even when issue actually
     * was moved between projects during your code execution.
     *
     * @see Issue::updateKey()
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getLabels(): array
    {
        return (array) ($this->getFieldValue('labels'));
    }

    /**
     * Get the most recent comment attached to issue
     *
     * @param bool $reload_cache - force data update. Get the freshest data possible from Jira API,
     *                           instead of using cache.
     *
     * @return \Mekras\Jira\Issue\Comment|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getLastComment($reload_cache = false): ?\Mekras\Jira\Issue\Comment
    {
        $comments = $this->getComments($reload_cache);
        $LastComment = end($comments);
        if ($LastComment === false) {
            return null;
        }

        return $LastComment;
    }

    public function getLinksList(): \Mekras\Jira\Issue\LinksList
    {
        $cache_key = 'Links';

        $LinksList = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $links = $this->getFieldValue('issuelinks') ?? [];
            $LinksList = \Mekras\Jira\Issue\LinksList::fromStdClass($links, $this);

            $this->cacheData($cache_key, $LinksList);
        }

        return $LinksList;
    }

    /**
     * @return static|null
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getParentIssue(): ?Issue
    {
        if (!$this->isSubIssue()) {
            return null;
        }

        return static::byKey($this->jiraClient, $this->getFieldValue('parent')->key, [], []);
    }

    /**
     * Get list of keys issue had before the current one.
     * Issue key is changed each time issue is moved from project to project.
     *
     * NOTE: the list _does_not_ include the current issue key.
     *
     * @return string[] - list of old issue keys, ordered from oldest to latest:
     *                     - the key issue had at the moment of creation is listed first
     *                     - the key issue had before the current one is listed last
     */
    public function getPrevKeys(): array
    {
        $previous_keys = [];

        foreach ($this->getHistory()->trackField('Key') as $Change) {
            if ($Change->isStringChanged()) {
                $previous_keys[] = $Change->getFromString();
            }
        }

        return $previous_keys;
    }

    /**
     * @return Priority|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getPriority(): ?Priority
    {
        $cache_key = 'Priority';

        $Priority = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $PriorityInfo = $this->getFieldValue('priority');

            if (isset($PriorityInfo)) {
                $Priority = Priority::fromStdClass(
                    $this->jiraClient,
                    $PriorityInfo,
                    $this
                );
            } else {
                // Issue has no priority. Yes, this is possible.
                $Priority = null;
            }

            $this->cacheData($cache_key, $Priority);
        }

        return $Priority;
    }

    public function getProject(): string
    {
        if (!isset($this->project)) {
            [$project_key] = explode('-', $this->getKey());
            $this->project = $project_key;
        }

        return $this->project;
    }

    /**
     * Get rendered field value, as it will be shown in JIRA Web interface.
     * This is useful e.g. for text fields with formatters (e.g. wiki-), to get the same layout as
     * in issue view page without tricks and hacks.
     *
     * @param string $field_id - ID of field to get (e.g. 'customfield_12345' or 'summary')
     *
     * @return string|null - null when no field with such ID found for issue.
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getRenderedField(string $field_id): ?string
    {
        return $this->getBaseIssue(
                [\Mekras\Jira\REST\Section\Issue::EXP_RENDERED_FIELDS]
            )->renderedFields->{$field_id} ?? null;
    }

    /**
     * Get issue's reporter user information. 'null' is returned for issues, where reporter is not
     * displayed.
     *
     * @return User|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getReporter(): ?User
    {
        $Reporter = $this->getCachedData('Reporter');

        if (!isset($Reporter) && $this->getFieldValue('reporter') !== null) {
            $Reporter = User::fromStdClass(
                $this->jiraClient,
                $this->getFieldValue('reporter'),
                $this
            );
            $this->cacheData('Reporter', $Reporter);
        }

        return $Reporter;
    }

    /**
     * Get current issue resolution. Returns null for unresolved issues.
     *
     * @return Resolution|null
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getResolution(): ?Resolution
    {
        $cache_key = 'Resolution';

        $Resolution = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $ResolutionInfo = $this->getFieldValue('resolution');

            if (isset($ResolutionInfo)) {
                $Resolution = Resolution::fromStdClass(
                    $this->jiraClient,
                    $ResolutionInfo,
                    $this
                );
            } else {
                // Issue has no resolution yet (unresolved)
                $Resolution = null;
            }

            $this->cacheData($cache_key, $Resolution);
        }

        return $Resolution;
    }

    /**
     * @return int - issue resolution time unix timestamp (resolved at).
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function getResolutionDate(): int
    {
        return $this->getDateField('resolutiondate');
    }

    /**
     * @return Security|null
     *
     * @throws REST\Exception
     */
    public function getSecurity(): ?\Mekras\Jira\Security
    {
        $cache_key = 'Security';

        $Security = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $PriorityInfo = $this->getFieldValue('security');

            if (isset($PriorityInfo)) {
                $Security = \Mekras\Jira\Security::fromStdClass($PriorityInfo, $this, $this->jiraClient);
            } else {
                // Issue has no priority. Yes, this is possible.
                $Security = null;
            }

            $this->cacheData($cache_key, $Security);
        }

        return $Security;
    }

    /**
     * @return string
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getSelfUrl(): string
    {
        if ($this->isCached('self')) {
            return $this->getCachedData('self');
        }

        $self = (string) $this->getBaseIssue()->self;
        $this->cacheData('self', $self);

        return $self;
    }

    /**
     * @return Status
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getStatus(): Status
    {
        $cache_key = 'Status';

        $Status = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $Status = Status::fromStdClass(
                $this->jiraClient,
                $this->getFieldValue('status'),
                $this
            );
            $this->cacheData($cache_key, $Status);
        }

        return $Status;
    }

    /**
     * @return \Mekras\Jira\Issue[]
     *
     * @return static[]
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getSubIssues(): array
    {
        return static::search($this->jiraClient, "parent = '{$this->getKey()}'", [], [], 1000, 0);
    }

    /**
     * @return string
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getSummary(): string
    {
        return (string) $this->getFieldValue('summary');
    }

    public function getTimeInLastStatus(): int
    {
        return $this->getHistory()->getTimeInLastStatus();
    }

    /**
     * @return \Mekras\Jira\Issue\Type
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getType(): \Mekras\Jira\Issue\Type
    {
        $cache_key = 'Type';

        $Type = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $Type = \Mekras\Jira\Issue\Type::fromStdClass(
                $this->jiraClient,
                $this->getFieldValue('issuetype'),
                $this
            );
            $this->cacheData($cache_key, $Type);
        }

        return $Type;
    }

    /**
     * @return int - issue update time unix timestamp
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Issue
     */
    public function getUpdatedDate()
    {
        return $this->getDateField('updated');
    }

    public function getUrl(): string
    {
        return $this->jiraClient->getRawClient()->getJiraUrl() . 'browse/' . $this->getKey();
    }

    /**
     * @return Version[]
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getVersions(): array
    {
        $Versions = $this->getCachedData('Versions');
        if (!isset($Versions)) {
            $Versions = [];

            $project_id = $this->getBaseIssue()->fields->project->id;
            foreach ((array) $this->getFieldValue('fixVersions') as $VersionInfo) {
                // Don't know why, but list of versions associated with issue has no 'projectId' field.
                $VersionInfo->projectId = $project_id;
                $Versions[] = Version::fromStdClass(
                    $this->getJiraClient(),
                    $VersionInfo,
                    $this
                );
            }
            $this->cacheData('Versions', $Versions);
        }

        return $Versions;
    }

    /**
     * Get list of issue watchers
     *
     * @return \Mekras\Jira\Issue\WatchersList
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception
     */
    public function getWatchers(): \Mekras\Jira\Issue\WatchersList
    {
        $cache_key = 'Watchers';

        $WatchersList = $this->getCachedData($cache_key);
        if (!isset($WatchersList)) {
            $watchers = $this->jiraClient->issue()->watchers()->list($this->getKey());

            $WatchersList = \Mekras\Jira\Issue\WatchersList::fromStdClass(
                $watchers,
                $this,
                $this->getJiraClient()
            );

            $this->cacheData($cache_key, $WatchersList);
        }

        return $WatchersList;
    }

    public function getWorkdaysInStatus(string $status): float
    {
        return $this->getHistory()->getWorkdaysInStatus($status);
    }

    /**
     * @return bool - true when issue has at least one subtask
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function hasSubIssues(): bool
    {
        return count($this->getFieldValue('subtasks')) > 0;
    }

    /**
     * @param string $field_id   - ID of system (e.g. 'description') or custom (e.g.
     *                           customfield_12345) field you want to check
     *
     * @return bool - true means you can update this issue field's value
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function isEditable(string $field_id): bool
    {
        return isset($this->getEditMeta()[$field_id]);
    }

    /**
     * @param string[] $check_projects - list of project keys, e.g. 'ABC', 'DEF', and so on.
     *
     * @return bool
     */
    public function isInProject(array $check_projects): bool
    {
        return in_array($this->getProject(), $check_projects);
    }

    /**
     * @return bool - true when issue has a parent
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function isSubIssue(): bool
    {
        return $this->getFieldValue('parent') !== null;
    }

    /**
     * Remove one or several components to issue
     *
     * @param string|int ...$components
     *
     * @return $this
     */
    public function removeComponents(...$components)
    {
        $components_update = $this->updateFields['components'] ?? [];

        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int) $component;
                $components_update[] = ['remove' => ['id' => "$component"]];
            } else {
                $components_update[] = ['remove' => ['name' => $component]];
            }
        }

        return $this->edit('components', $components_update);
    }

    /**
     * Apply all changes planned with ->edit() calls.
     * Actually updated JIRA issue fields
     * NOTE: this method call results in internal issue object cache drop. Any get<Field> call will
     * cause single JIRA API request to refresh the cache. All get<Field> calls will cause new
     * wrapper objects creation
     *
     * @param array $properties
     * @param bool  $notify_users
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Issue::edit DocBlock for more info about parameters meaning
     *
     */
    public function save(array $properties = [], $notify_users = true): \Mekras\Jira\Issue
    {
        if (empty($this->updateFields)) {
            return $this;
        }

        $this->jiraClient->issue()->edit(
            $this->getKey(),
            [],
            $this->updateFields,
            $properties,
            $notify_users
        );
        $this->updateFields = [];

        foreach ($this->customFields as $CustomField) {
            $CustomField->dropCache();
        }
        $this->dropCache();

        return $this;
    }

    /**
     * Assign issue to a user
     *
     * @param string|User|null $user   - user to set as assignee of issue.
     *                                 null - unassign issue
     *                                 "-1" - set assignee to project default
     *
     * @return $this
     */
    public function setAssignee($user): Issue
    {
        if ($user instanceof User) {
            $user = $user->getName();
        }

        return $this->edit(
            'assignee',
            [
                ['set' => ['name' => $user]],
            ]
        );
    }

    /**
     * Set list of issue components to exact ones, clearing previous field value
     *
     * @param string|int ...$components - names or IDs of components to add to issue
     *
     * @return $this
     */
    public function setComponents(...$components)
    {
        $set_components = [];
        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int) $component;
                $set_components[] = ['id' => "$component"];
            } else {
                $set_components[] = ['name' => $component];
            }
        }

        return $this->edit(
            'components',
            ['set' => $set_components]
        );
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description): Issue
    {
        return $this->edit(
            'description',
            [
                ['set' => $description],
            ]
        );
    }

    /**
     * Set issue labels removing all ones it had before
     *
     * @param string ...$labels
     *
     * @return $this
     */
    public function setLabels(string ...$labels): Issue
    {
        // We need array_values() here to drop keys preserving from array_unique()
        $new_value = array_values(array_unique($labels));

        return $this->edit(
            'labels',
            [
                ['set' => $new_value],
            ]
        );
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setPriority(int $id): Issue
    {
        return $this->edit(
            "priority",
            [
                ["set" => ["id" => (string) $id]],
            ]
        );
    }

    /**
     * Change issue's reporter
     *
     * @param string|User $user - user to set as issue reporter.
     *
     * @return $this
     */
    public function setReporter($user): Issue
    {
        if ($user instanceof User) {
            $user = $user->getName();
        }

        return $this->edit(
            'reporter',
            [
                ['set' => ['name' => $user]],
            ]
        );
    }

    /**
     * Change issue resolution
     *
     * @param int $id
     *
     * @return $this
     */
    public function setResolution(int $id): \Mekras\Jira\Issue
    {
        return $this->edit(
            'resolution',
            [
                ['set' => ['id' => (string) $id]],
            ]
        );
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setSecurity(int $id): \Mekras\Jira\Issue
    {
        return $this->edit(
            "security",
            [
                ["set" => ["id" => (string) $id]],
            ]
        );
    }

    /**
     * @param string $summary
     *
     * @return $this
     */
    public function setSummary(string $summary): Issue
    {
        return $this->edit(
            'summary',
            [
                ['set' => $summary],
            ]
        );
    }

    /**
     * Perform a transition on issue using step name instead of ID (the one displayed on button in
     * Web UI). All changes you scheduled by Issue->edit() will be applied during transition if you
     * not  called ->save() yet.
     *
     * @param string $step_name        - name of step you want to do
     * @param bool   $step_same_status - true: do transition even when it leads to the same status
     *                                 false: throw an exception if issue is already in target
     *                                 status
     * @param bool   $safe_transition  - drop changes for fields that can't be set during
     *                                 transition because of transition screen configuration.
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception - when JIRA rejected to do issue transition.
     */
    public function step(
        string $step_name,
        bool $step_same_status = false,
        bool $safe_transition = false
    ): \Mekras\Jira\Issue {
        $this->jiraClient->issue()->transitions()->step(
            $this->getKey(),
            $step_name,
            [],
            $this->updateFields,
            $safe_transition,
            $step_same_status
        );

        $this->updateFields = [];
        $this->dropCache();

        return $this;
    }

    /**
     * Perform a transition on issue. All changes you scheduled by Issue->edit() will be applied
     * during transition if you not  called ->save() yet.
     *
     * @param int  $transition_id     - ID of transition to do
     * @param bool $safe_transition   - perform safe transition: siletly filter out field changes,
     *                                that are not allowed by transition screen.
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception - when JIRA rejected to do issue transition.
     */
    public function transition(int $transition_id, $safe_transition = false): \Mekras\Jira\Issue
    {
        if ($safe_transition) {
            $this->jiraClient->issue()->transitions()->do_safe(
                $this->getKey(),
                $transition_id,
                [],
                $this->updateFields
            );
        } else {
            $this->jiraClient->issue()->transitions()->do(
                $this->getKey(),
                $transition_id,
                [],
                $this->updateFields
            );
        }

        $this->updateFields = [];
        $this->dropCache();

        return $this;
    }

    /**
     * Update issue key to the latest one.
     * This is not done automatically each time fresh data is loaded from API to prevent unexpected
     * changes of key during execution, when code silently updates data from API
     *
     * @param bool $reload_cache   - drop all internal caches of Issue object before getting fresh
     *                             key. This causes full data reload from API, with the same
     *                             expands issue had before drop.
     *
     * @throws REST\Exception
     */
    public function updateKey(bool $reload_cache = false)
    {
        if ($reload_cache) {
            $this->dropCache();
        }

        $this->key = $this->getBaseIssue()->key;
    }

    /**
     * Put data portion into internal object cache, storing it under <key> key
     * NOTE: if another portion of data already exists under the same key, it will be silently
     * overwritten.
     *
     * @param string $key   - key to store data
     * @param mixed  $value - data to be stored
     *
     * @return $this
     */
    protected function cacheData($key, $value): Issue
    {
        $this->cachedData[$key] = $value;

        return $this;
    }

    /**
     * Drop all cached data. After that any ::get* method call will cause new Jira API request.
     */
    protected function dropCache()
    {
        $this->baseIssue = null;
        $this->customFields = [];
        $this->cachedData = [];
    }

    /**
     * @param string[] $expand - load extra information for issue
     *
     * @return \stdClass
     *
     * @throws \Mekras\Jira\REST\Exception
     * @see \Mekras\Jira\REST\Section\Issue::EXP_CHANGELOG
     *
     */
    protected function getBaseIssue(array $expand = []): \stdCLass
    {
        foreach ($expand as $group) {
            if (!in_array($group, $this->expands)) {
                // Force cache update when groups required to be expanded are absent in current cache.
                $this->dropCache();
                $this->expands[] = $group;
            }
        }

        if (!isset($this->baseIssue)) {
            $this->dropCache();
            $this->baseIssue = $this->jiraClient->issue()->get($this->getKey(), [], $this->expands);
        }

        return $this->baseIssue;
    }

    /**
     * @param string $key
     *
     * @return mixed - returns cached data when given key exists, null when no data was cached with
     *               given key
     */
    protected function getCachedData($key)
    {
        return $this->cachedData[$key] ?? null;
    }

    /**
     * Check if data under key <key> is available in cache.
     * NOTE: checks only for key existence, returns true even when value associated with key is
     * 'null' or 'false'.
     *
     * @param string $key - key to check in cache
     *
     * @return bool - true when key with name <key> exists in cache
     */
    protected function isCached($key): bool
    {
        return array_key_exists($key, $this->cachedData);
    }
}
