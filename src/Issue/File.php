<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\Issue;

use Mekras\Jira\Issue;
use Mekras\Jira\REST\Client;
use Mekras\Jira\User;

class File
{
    /** @var Client */
    protected $Jira;

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var Issue */
    protected $Issue;

    /** @var int $id */
    protected $id;

    /** @var array */
    protected $cache = [];

    public static function fromStdClass(
        Client $jiraClient,
        \stdClass $AttachmentInfo,
        Issue $Issue
    ) : File {
        $Instance = new static($jiraClient, (int) $AttachmentInfo->id, $Issue);
        $Instance->OriginalObject = $AttachmentInfo;

        return $Instance;
    }

    public function __construct(Client $jiraClient, int $id, Issue $Issue = null)
    {
        $this->id = $id;
        $this->Issue = $Issue;
        $this->Jira = $jiraClient;
    }

    protected function getOriginalObject() : \stdClass
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Jira->attachment()->get($this->id);
        }

        return $this->OriginalObject;
    }

    protected function dropCache() : void
    {
        $this->OriginalObject = null;
        $this->cache = [];
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function getName() : string
    {
        return $this->getOriginalObject()->filename;
    }

    public function getSize() : int
    {
        return (int)$this->getOriginalObject()->size;
    }

    public function getMimeType() : string
    {
        return $this->getOriginalObject()->mimeType;
    }

    public function getContentLink() : string
    {
        return $this->getOriginalObject()->content;
    }

    public function getThumbnailLink() : string
    {
        return $this->getOriginalObject()->thumbnail;
    }

    public function getCreated() : int
    {
        $key = 'created';

        if (!isset($this->cache[$key])) {
            $time = $this->getOriginalObject()->created;
            $this->cache[$key] = (int)strtotime($time);
        }

        return $this->cache[$key];
    }

    public function getAuthor() : User
    {
        $key = 'Author';

        if (!isset($this->cache[$key])) {
            $UserInfo = $this->getOriginalObject()->author;
            $this->cache[$key] = User::fromStdClass($this->Jira, $UserInfo, $this->Issue);
        }

        return $this->cache[$key];
    }

    public function delete()
    {
        $this->Jira->attachment()->delete($this->getId());
    }
}
