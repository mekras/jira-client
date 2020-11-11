<?php

declare(strict_types=1);

/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

use Badoo\Jira\REST\ClientRaw;

/**
 * TODO Describe.
 *
 * @since x.x
 */
class Section
{
    /**
     * Raw jira client.
     *
     * @var ClientRaw
     */
    protected $jira;

    /**
     * TODO ???
     *
     * @var Section[]
     */
    protected $sections = [];

    /**
     * Construct section.
     *
     * @param ClientRaw $jira Raw jira client.
     */
    public function __construct(ClientRaw $jira)
    {
        $this->jira = $jira;
    }

    /**
     * @param string $sectionKey   The unique section key for cache. This prevents twin
     *                             objects creation for the same section on each method call.
     * @param string $sectionClass Use special custom class for given section. E. g.
     *                             ->getSubSection('/issue',
     *                             '\Badoo\Jira\REST\Section\Issue') will initialize and return
     *                             \Badoo\Jira\REST\Section\Issue class for section /issue.
     *
     * @return self
     */
    protected function getSection(string $sectionKey, string $sectionClass): self
    {
        if (!isset($this->sections[$sectionKey])) {
            $Section = new $sectionClass($this->jira, $sectionKey);
            $this->sections[$sectionKey] = $Section;
        }

        return $this->sections[$sectionKey];
    }
}
