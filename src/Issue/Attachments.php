<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

class Attachments
{
    /** @var \Mekras\Jira\Issue */
    protected $Issue;

    /** @var File[] */
    protected $files;

    public static function fromStdClass(
        array $files,
        \Mekras\Jira\Issue $Issue
    ) : Attachments {
        $Instance = new static($Issue);

        foreach ($files as $AttachmentInfo) {
            $Instance->files[] = File::fromStdClass($AttachmentInfo, $Issue, $Issue->getJira());
        }

        return $Instance;
    }

    public static function forIssue(string $issue_key, \Mekras\Jira\REST\Client $Jira = null) : Attachments
    {
        $Issue = \Mekras\Jira\Issue::byKey($issue_key, ['attachment'], [], $Jira);
        return $Issue->getAttachments();
    }

    public function __construct(\Mekras\Jira\Issue $Issue)
    {
        $this->Issue = $Issue;
    }

    protected function getJira() : \Mekras\Jira\REST\Client
    {
        return $this->Issue->getJira();
    }

    /**
     * @return File[]
     * @throws \Mekras\Jira\REST\Exception
     */
    public function getFiles() : array
    {
        if (!isset($this->files)) {
            $this->files = [];

            $attachments = $this->getJira()->issue()->attachment()->list($this->Issue->getKey());
            foreach ($attachments as $AttachmentInfo) {
                $this->files[] = File::fromStdClass($AttachmentInfo, $this->Issue, $this->getJira());
            }
        }

        return $this->files;
    }

    public function attach(string $file_path, ?string $file_name = null, ?string $file_type = null) : File
    {
        if (!\Mekras\Jira\Helpers\Files::exists($file_path)) {
            throw new \Mekras\Jira\Exception\File(
                "File {$file_path} not found on disk. Can't upload it to JIRA"
            );
        }

        $AttachmentInfo = $this->getJira()->issue()->attachment()->create($this->Issue->getKey(), $file_path, $file_name, $file_type);
        $File = File::fromStdClass($AttachmentInfo, $this->Issue, $this->getJira());

        if (isset($this->files)) {
            $this->files[] = $File;
        }

        return $File;
    }
}
