<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

/**
 * Interface ILogRecord
 *
 * Each issue log record must have information about it's creation time and it's issue.
 */
interface ILogRecord
{
    /**
     * Log record parent's issue.
     * @return \Mekras\Jira\Issue
     */
    public function getIssue() : \Mekras\Jira\Issue;

    /**
     * Log record creation time.
     * @return int
     */
    public function getCreated() : int;

    /**
     * List of items (changes made to issue) within this log record.
     * @return LogRecordItem[]
     */
    public function getItems() : array;
}
