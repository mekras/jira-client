<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST;

use Mekras\Jira\REST\Section\Section;

class Client extends Section
{
    /**
     * @var static|null
     */
    protected static $instance;

    /**
     * @return static
     */
    public static function instance() : Client
    {
        if (!isset(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Client constructor.
     *
     * @param string         $jira_url
     * @param string         $api_prefix
     * @param ClientRaw|null $Jira Instance of ClientRaw to use instead of default one.
     */
    public function __construct(
        $jira_url   = ClientRaw::DEFAULT_JIRA_URL,
        $api_prefix = ClientRaw::DEFAULT_JIRA_API_PREFIX,
        ClientRaw $Jira = null
    ) {
        if ($Jira === null) {
            $Jira = ClientRaw::instance()
                ->setJiraUrl($jira_url)
                ->setApiPrefix($api_prefix);
        }

        parent::__construct($Jira);
    }

    /**
     * @return ClientRaw
     */
    public function getRawClient() : ClientRaw
    {
        return $this->jira;
    }

    /**
     * @param string $url
     * @return static
     */
    public function setJiraUrl(string $url) : Client
    {
        $this->getRawClient()->setJiraUrl($url);
        return $this;
    }

    /**
     * @param string $login
     * @param string $secret
     * @return static
     */
    public function setAuth(string $login, string $secret) : Client
    {
        $this->getRawClient()->setAuth($login, $secret);
        return $this;
    }

    /**
     * @return \Mekras\Jira\REST\Section\Jql
     */
    public function jql() : \Mekras\Jira\REST\Section\Jql
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('jql', \Mekras\Jira\REST\Section\Jql::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\Attachment
     */
    public function attachment() : \Mekras\Jira\REST\Section\Attachment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('attachment', \Mekras\Jira\REST\Section\Attachment::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\Project
     */
    public function project() : \Mekras\Jira\REST\Section\Project
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('project', \Mekras\Jira\REST\Section\Project::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\Component
     */
    public function component() : \Mekras\Jira\REST\Section\Component
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('component', \Mekras\Jira\REST\Section\Component::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\Issue
     */
    public function issue() : \Mekras\Jira\REST\Section\Issue
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issue', \Mekras\Jira\REST\Section\Issue::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\IssueType
     */
    public function issueType() : \Mekras\Jira\REST\Section\IssueType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuetype', \Mekras\Jira\REST\Section\IssueType::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\IssueLink
     */
    public function issueLink() : \Mekras\Jira\REST\Section\IssueLink
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelink', \Mekras\Jira\REST\Section\IssueLink::class);
    }

    /**
     * @return \Mekras\Jira\REST\Section\IssueLinkType
     */
    public function issueLinkType() : \Mekras\Jira\REST\Section\IssueLinkType
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('issuelinktype', \Mekras\Jira\REST\Section\IssueLinkType::class);
    }

    /**
     * Search for issues using JQL.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/search-search
     *
     * @param string    $jql
     * @param string[]  $fields
     * @param string[]  $expand
     * @param int       $max_results
     * @param int       $start_at
     * @param bool      $validate_query
     *
     * @return \stdClass[] - API response, parsed as JSON. You need to use 'issues' key to get actual list of issues from response
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function search(string $jql, $fields = [], $expand = [], int $max_results = 50, int $start_at = 0, $validate_query = true)
    {
        $args = [
            'jql'           => $jql,
            'startAt'       => $start_at,
            'maxResults'    => $max_results,
            'validateQuery' => $validate_query,
        ];

        if (!empty($fields)) {
            $args['fields'] = $fields;
        }
        if (!empty($expand)) {
            $args['expand'] = $expand;
        }

        return $this->jira->post('/search', $args);
    }

    /**
     * Get interface for operations with Jira issue fields
     */
    public function field() : \Mekras\Jira\REST\Section\Field
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('field', \Mekras\Jira\REST\Section\Field::class);
    }

    /**
     * Get interface for operations with Jira resolutions
     */
    public function resolution() : \Mekras\Jira\REST\Section\Resolution
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('resolution', \Mekras\Jira\REST\Section\Resolution::class);
    }

    /**
     * Get interface for operations with JIRA users
     */
    public function user() : \Mekras\Jira\REST\Section\User
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('user', \Mekras\Jira\REST\Section\User::class);
    }

    /**
     * Get interface for operations with JIRA user groups
     */
    public function group() : \Mekras\Jira\REST\Section\Group
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('group', \Mekras\Jira\REST\Section\Group::class);
    }

    /**
     * Get interface for operations with JIRA issue security levels
     */
    public function securityLevel() : \Mekras\Jira\REST\Section\SecurityLevel
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('securitylevel', \Mekras\Jira\REST\Section\SecurityLevel::class);
    }

    /**
     * Get interface for operations with JIRA issue priorities
     */
    public function priority() : \Mekras\Jira\REST\Section\Priority
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('priority', \Mekras\Jira\REST\Section\Priority::class);
    }

    /**
     * Get interface for operations with JIRA status categories
     */
    public function statusCategory() : \Mekras\Jira\REST\Section\StatusCategory
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('statuscategory', \Mekras\Jira\REST\Section\StatusCategory::class);
    }

    /**
     * Get interface for operations with JIRA statuses
     */
    public function status() : \Mekras\Jira\REST\Section\Status
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('status', \Mekras\Jira\REST\Section\Status::class);
    }

    public function version() : \Mekras\Jira\REST\Section\Version
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('version', \Mekras\Jira\REST\Section\Version::class);
    }
}
