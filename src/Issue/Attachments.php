<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;

class Attachments
{
    /** @var Issue */
    protected $Issue;

    /** @var File[] */
    protected $files;

    public static function fromStdClass(
        array $files,
        Issue $Issue
    ): Attachments {
        $Instance = new static($Issue);

        foreach ($files as $AttachmentInfo) {
            $Instance->files[] = File::fromStdClass($Issue->getJiraClient(), $AttachmentInfo, $Issue);
        }

        return $Instance;
    }

    public static function forIssue(Client $jiraClient, string $issueKey): Attachments
    {
        $Issue = Issue::byKey($jiraClient, $issueKey, ['attachment'], []);

        return $Issue->getAttachments();
    }

    public function __construct(Issue $Issue)
    {
        $this->Issue = $Issue;
    }

    protected function getJira(): Client
    {
        return $this->Issue->getJiraClient();
    }

    /**
     * @return File[]
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getFiles(): array
    {
        if (!isset($this->files)) {
            $this->files = [];

            $attachments = $this->getJira()->issue()->attachment()->list($this->Issue->getKey());
            foreach ($attachments as $AttachmentInfo) {
                $this->files[] = File::fromStdClass(
                    $this->getJira(),
                    $AttachmentInfo,
                    $this->Issue
                );
            }
        }

        return $this->files;
    }

    public function attach(
        string $file_path,
        ?string $file_name = null,
        ?string $file_type = null
    ): File {
        if (!\Mekras\Jira\Helpers\Files::exists($file_path)) {
            throw new \Mekras\Jira\Exception\File(
                "File {$file_path} not found on disk. Can't upload it to JIRA"
            );
        }

        $AttachmentInfo = $this->getJira()->issue()->attachment()->create(
            $this->Issue->getKey(),
            $file_path,
            $file_name,
            $file_type
        );
        $File = File::fromStdClass($this->getJira(), $AttachmentInfo, $this->Issue);

        if (isset($this->files)) {
            $this->files[] = $File;
        }

        return $File;
    }
}
