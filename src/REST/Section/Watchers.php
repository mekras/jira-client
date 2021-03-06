<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Watchers extends Section
{
    /**
     * Add watcher to issue
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-addWatcher
     *
     * @param string $issue_key
     * @param string $user_login
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function add(string $issue_key, string $user_login): void
    {
        $this->rawClient->post("issue/{$issue_key}/watchers", $user_login);
    }

    /**
     * List issue watchers
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getIssueWatchers
     *
     * @param string $issue_key
     *
     * @return \stdClass[] - list of <Jira user info> objects
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function list(string $issue_key)
    {
        $response = $this->rawClient->get("issue/{$issue_key}/watchers");

        if (!isset($response->watchers)) {
            return [];
        }

        return $response->watchers;
    }

    /**
     * Stop watching issue
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-removeWatcher
     *
     * @param string $issue_key
     * @param string $user_login
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function remove(string $issue_key, string $user_login): void
    {
        $this->rawClient->delete("issue/{$issue_key}/watchers", ['username' => $user_login]);
    }
}
