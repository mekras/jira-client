<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class LinksList
{
    /** @var Issue */
    protected $Issue;

    /** @var Link[] */
    protected $links;

    /**
     * Initialize LinksList object on data of Issue->fields->issuelinks obtained from API
     *
     * @param \stdClass[] $links            - links list information as it is provided in Issue->fields->issueLinks
     * @param Issue $Issue      - when current LinksList object represents current links list of some issue.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Link
     */
    public static function fromStdClass(
        array $links,
        Issue $Issue
    ) : LinksList {
        $Instance = new static($Issue);

        foreach ($links as $LinkInfo) {
            $Link = Link::fromIssueField($LinkInfo, $Issue);
            $Instance->links[$Link->getId()] = $Link;
        }

        return $Instance;
    }

    /**
     * Get list of links connected to issue identified by key.
     *
     * @param Client $jiraClient JIRA API client to use.
     * @param string $issueKey   Key of issue to list links for.
     *
     * @return static
     *
     * @throws \Mekras\Jira\Exception\Issue
     * @throws \Mekras\Jira\REST\Exception
     */
    public static function forIssue(Client $jiraClient, string $issueKey) : LinksList
    {
        $Issue = Issue::byKey($jiraClient, $issueKey, ['issuelinks'], []);
        return $Issue->getLinksList();
    }

    public function __construct(Issue $Issue)
    {
        $this->Issue = $Issue;
    }

    /**
     * Drop internal object cache
     * @return $this
     */
    public function dropCache() : LinksList
    {
        $this->links = null;
        return $this;
    }

    public function getIssue() : Issue
    {
        return $this->Issue;
    }

    /**
     * @param string $issue_key
     * @param string $link_type
     * @param string $comment
     * @param array $visibility
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function addInward(string $issue_key, string $link_type, string $comment = '', array $visibility = []) : LinksList
    {
        $this->Issue->getJiraClient()->issueLink()->create(
            $link_type,
            $this->Issue->getKey(),
            $issue_key,
            $comment,
            $visibility
        );

        $this->dropCache();

        return $this;
    }

    /**
     * Add link from current issue (returned by ::getIssue();) to $issue_key.
     * Outward description of link will be displayed on this issue's page.
     *
     * @param string $issue_key
     * @param string $link_type
     * @param string $comment
     * @param array $visibility
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function addOutward(string $issue_key, string $link_type, string $comment = '', array $visibility = []) : LinksList
    {
        $this->Issue->getJiraClient()->issueLink()->create(
            $link_type,
            $issue_key,
            $this->Issue->getKey(),
            $comment,
            $visibility
        );

        $this->dropCache();

        return $this;
    }

    /**
     * @param int|string $type - type name or ID. When not empty - only links with this type will be returned
     * @param bool $case_sensitive - perform case-sensitive search when using type name
     *
     * @return Link[]
     *
     * @throws \Mekras\Jira\Exception\Link
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getLinks($type = '', bool $case_sensitive = true) : array
    {
        if (!isset($this->links)) {
            $this->links = [];

            $links = $this->Issue->getFieldValue('issuelinks');
            foreach ($links as $LinkInfo) {
                $this->links[] = Link::fromIssueField($LinkInfo, $this->Issue);
            }
        }

        if (empty($type)) {
            // No type filtration, tell everything we know :)
            return $this->links;
        }

        $by_type = [];

        if (is_numeric($type)) {
            // $type is ID
            $type = (int)$type;
            foreach ($this->getLinks() as $Link) {
                if ($Link->getType()->getId() === $type) {
                    $by_type[$Link->getId()] = $Link;
                }
            }

            return $by_type;
        }

        // $type is a textual name

        if ($case_sensitive) {
            foreach ($this->getLinks() as $Link) {
                if ($Link->getType()->getName() === $type) {
                    $by_type[$Link->getId()] = $Link;
                }
            }

            return $by_type;
        }

        $type = strtolower($type);

        foreach ($this->getLinks() as $Link) {
            if (strtolower($Link->getType()->getName()) === $type) {
                $by_type[$Link->getId()] = $Link;
            }
        }

        return $by_type;
    }

    /**
     * @return Link[]
     *
     * @throws \Mekras\Jira\Exception\Link
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getLinksInward() : array
    {
        $inward = [];
        foreach ($this->getLinks() as $Link) {
            if ($Link->getOutwardIssue()->getKey() === $this->getIssue()->getKey()) {
                $inward[$Link->getId()] = $Link;
            }
        }

        return $inward;
    }

    /**
     * @return Link[]
     *
     * @throws \Mekras\Jira\Exception\Link
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getLinksOutward() : array
    {
        $inward = [];
        foreach ($this->getLinks() as $Link) {
            if ($Link->getInwardIssue()->getKey() === $this->getIssue()->getKey()) {
                $inward[$Link->getId()] = $Link;
            }
        }

        return $inward;
    }

    /**
     * Remove link between current issue and other one. Don't check the direction of link at all.
     *
     * @param string $issue_key
     * @param string|int $link_type - remove only link of this type (ID or name)
     * @param bool $case_sensitive - perform case-sensitive search when using type name
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Link
     */
    public function removeLink(string $issue_key, $link_type = '', bool $case_sensitive = true) : LinksList
    {
        if (!$case_sensitive && !is_numeric($link_type)) {
            $link_type = strtolower($link_type);
        }

        foreach ($this->getLinks() as $Link) {
            if ($Link->getInwardIssue()->getKey() !== $issue_key && $Link->getOutwardIssue()->getKey() !== $issue_key) {
                continue;
            }

            if (empty($link_type)) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
                continue;
            }

            $LinkType = $Link->getType();

            $type_name = $LinkType->getName();
            if ($case_sensitive) {
                $type_name = strtolower($type_name);
            }

            if ($type_name == $link_type || $LinkType->getId() == $link_type) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
            }
        }

        return $this;
    }

    /**
     * Remove link with issue, that is displayed on page with Inward Description
     *
     * @param string $issue_key
     * @param string|int $link_type - remove only link of this type (ID or name)
     * @param bool $case_sensitive - perform case-sensitive search when using type name
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Link
     */
    public function removeLinkInward(string $issue_key, $link_type = '', bool $case_sensitive = true) : LinksList
    {
        if (!$case_sensitive && !is_numeric($link_type)) {
            $link_type = strtolower($link_type);
        }

        foreach ($this->getLinks() as $Link) {
            if ($Link->getInwardIssue()->getKey() !== $issue_key) {
                continue;
            }

            if (empty($link_type)) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
                continue;
            }

            $LinkType = $Link->getType();

            $type_name = $LinkType->getName();
            if ($case_sensitive) {
                $type_name = strtolower($type_name);
            }

            if ($type_name == $link_type || $LinkType->getId() == $link_type) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
            }
        }

        return $this;
    }

    /**
     * Remove link with issue, that is displayed on page with Inward Description
     *
     * @param string $issue_key
     * @param string|int $link_type - remove only link of this type (ID or name)
     * @param bool $case_sensitive - perform case-sensitive search when using type name
     *
     * @return $this
     *
     * @throws \Mekras\Jira\REST\Exception
     * @throws \Mekras\Jira\Exception\Link
     */
    public function removeLinkOutward(string $issue_key, $link_type = '', bool $case_sensitive = true) : LinksList
    {
        if (!$case_sensitive && !is_numeric($link_type)) {
            $link_type = strtolower($link_type);
        }

        foreach ($this->getLinks() as $Link) {
            if ($Link->getOutwardIssue()->getKey() !== $issue_key) {
                continue;
            }

            if (empty($link_type)) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
                continue;
            }

            $LinkType = $Link->getType();

            $type_name = $LinkType->getName();
            if ($case_sensitive) {
                $type_name = strtolower($type_name);
            }

            if ($type_name == $link_type || $LinkType->getId() == $link_type) {
                $Link->delete();
                unset($this->links[$Link->getId()]);
            }
        }

        return $this;
    }
}
