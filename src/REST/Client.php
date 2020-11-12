<?php

declare(strict_types=1);

/**
 * @author  Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST;

use Mekras\Jira\Cache\NullCache;
use Mekras\Jira\REST\HTTP\CurlClient;
use Mekras\Jira\REST\Section\Attachment;
use Mekras\Jira\REST\Section\Component;
use Mekras\Jira\REST\Section\Field;
use Mekras\Jira\REST\Section\Group;
use Mekras\Jira\REST\Section\Issue;
use Mekras\Jira\REST\Section\IssueLink;
use Mekras\Jira\REST\Section\IssueLinkType;
use Mekras\Jira\REST\Section\IssueType;
use Mekras\Jira\REST\Section\Jql;
use Mekras\Jira\REST\Section\Priority;
use Mekras\Jira\REST\Section\Project;
use Mekras\Jira\REST\Section\Resolution;
use Mekras\Jira\REST\Section\Section;
use Mekras\Jira\REST\Section\SecurityLevel;
use Mekras\Jira\REST\Section\Status;
use Mekras\Jira\REST\Section\StatusCategory;
use Mekras\Jira\REST\Section\User;
use Mekras\Jira\REST\Section\Version;

/**
 * TODO ???
 *
 * @since x.x
 */
class Client extends Section
{
    /**
     * Client constructor.
     *
     * @param string         $jiraUrl
     * @param string         $apiPrefix
     * @param ClientRaw|null $rawClient Instance of ClientRaw to use instead of default one.
     */
    public function __construct(
        string $jiraUrl = 'https://jira.localhost/',
        string $apiPrefix = '/rest/api/latest/',
        ClientRaw $rawClient = null
    ) {
        if ($rawClient === null) {
            $rawClient = new ClientRaw($jiraUrl, $apiPrefix, new CurlClient(), new NullCache());
        }

        parent::__construct($rawClient);
    }

    /**
     * @return Attachment
     */
    public function attachment(): Attachment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('attachment', Attachment::class);
    }

    /**
     * @return Component
     */
    public function component(): Component
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('component', Component::class);
    }

    /**
     * Get interface for operations with Jira issue fields
     */
    public function field(): Field
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('field', Field::class);
    }

    /**
     * @return ClientRaw
     */
    public function getRawClient(): ClientRaw
    {
        return $this->rawClient;
    }

    /**
     * Get interface for operations with JIRA user groups
     */
    public function group(): Group
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('group', Group::class);
    }

    /**
     * @return Issue
     */
    public function issue(): Issue
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issue', Issue::class);
    }

    /**
     * @return IssueLink
     */
    public function issueLink(): IssueLink
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelink', IssueLink::class);
    }

    /**
     * @return IssueLinkType
     */
    public function issueLinkType(): IssueLinkType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelinktype', IssueLinkType::class);
    }

    /**
     * @return IssueType
     */
    public function issueType(): IssueType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuetype', IssueType::class);
    }

    /**
     * @return Jql
     */
    public function jql(): Jql
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('jql', Jql::class);
    }

    /**
     * Get interface for operations with JIRA issue priorities
     */
    public function priority(): Priority
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('priority', Priority::class);
    }

    /**
     * @return Project
     */
    public function project(): Project
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('project', Project::class);
    }

    /**
     * Get interface for operations with Jira resolutions
     */
    public function resolution(): Resolution
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('resolution', Resolution::class);
    }

    /**
     * Search for issues using JQL.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/search-search
     *
     * @param string   $jql
     * @param string[] $fields
     * @param string[] $expand
     * @param int      $max_results
     * @param int      $start_at
     * @param bool     $validate_query
     *
     * @return \stdClass[] - API response, parsed as JSON. You need to use 'issues' key to get
     *                     actual list of issues from response
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function search(
        string $jql,
        $fields = [],
        $expand = [],
        int $max_results = 50,
        int $start_at = 0,
        $validate_query = true
    ): array {
        $args = [
            'jql' => $jql,
            'startAt' => $start_at,
            'maxResults' => $max_results,
            'validateQuery' => $validate_query,
        ];

        if (!empty($fields)) {
            $args['fields'] = $fields;
        }
        if (!empty($expand)) {
            $args['expand'] = $expand;
        }

        return $this->rawClient->post('/search', $args);
    }

    /**
     * Get interface for operations with JIRA issue security levels
     */
    public function securityLevel(): SecurityLevel
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('securitylevel', SecurityLevel::class);
    }

    /**
     * @param string $login
     * @param string $secret
     *
     * @return static
     */
    public function setAuth(string $login, string $secret): Client
    {
        $this->getRawClient()->setAuth($login, $secret);

        return $this;
    }

    /**
     * Get interface for operations with JIRA statuses
     */
    public function status(): Status
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('status', Status::class);
    }

    /**
     * Get interface for operations with JIRA status categories
     */
    public function statusCategory(): StatusCategory
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('statuscategory', StatusCategory::class);
    }

    /**
     * Get interface for operations with JIRA users
     */
    public function user(): User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('user', User::class);
    }

    public function version(): Version
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('version', Version::class);
    }
}
