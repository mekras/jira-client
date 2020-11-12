<?php

declare(strict_types=1);

namespace Mekras\Jira\Tests\Unit\REST\Section;

use Mekras\Jira\REST\ClientRaw;
use Mekras\Jira\REST\Section\Issue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Tests for Issue.
 *
 * @covers \Mekras\Jira\REST\Section\Issue
 */
class IssueTest extends TestCase
{
    /**
     * Test that search method uses cache to store results.
     *
     * @throws \Throwable
     */
    public function testSearchUsesCache(): void
    {
        $issue1 = new \stdClass();
        $issue1->key = 'FOO-1';
        $issue2 = new \stdClass();
        $issue2->key = 'FOO-2';

        $searchResult = new \stdClass();
        $searchResult->issues = [$issue1, $issue2];

        $client = $this->createMock(ClientRaw::class);
        $client
            ->expects(self::once())
            ->method('post')
            ->with(
                self::equalTo('/search'),
                self::equalTo(
                    [
                        'jql' => 'project = FOO',
                        'startAt' => 0,
                        'maxResults' => 1000,
                        'validateQuery' => true,
                    ]
                )
            )
        ->willReturn($searchResult);

        $cache = new Psr16Cache(new ArrayAdapter());

        $issue = new Issue($client, $cache);

        // Request from Jira.
        $issues = $issue->search('project = FOO');
        self::assertEquals([$issue1, $issue2], $issues);

        // Now, load from cache.
        $issues = $issue->search('project = FOO');
        self::assertEquals([$issue1, $issue2], $issues);
    }
}
