<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST\Section;

final class Attachment extends Section
{
    /**
     * Delete attachment file from JIRA.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/attachment-removeAttachment
     *
     * @param int $id - ID of file to delete
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function delete(int $id): void
    {
        $this->rawClient->delete("attachment/{$id}");
    }

    /**
     * Get attachment file metadata by file ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/attachment-getAttachment
     *
     * @param int $id - ID of attachment you want to load
     *
     * @return \stdClass - attachment metadata
     *
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(int $id): \stdClass
    {
        return $this->rawClient->get("attachment/{$id}");
    }
}
