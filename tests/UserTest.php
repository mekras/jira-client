<?php

declare(strict_types=1);

namespace Mekras\Jira\Tests;

use Mekras\Jira\REST\Client;
use Mekras\Jira\REST\ClientRaw;
use Mekras\Jira\User;

/**
 * Tests for User class.
 *
 * @covers \Mekras\Jira\User
 */
class UserTest extends BaseTestCase
{
    /**
     * Data provider for testCreateUserFromObject.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function jiraUserDataProvider(): array
    {
        return [
            'Jira Server' => [
                'jiraUserData' => self::createCustomObject(
                    [
                        'name' => 'username',
                    ]
                ),
                'expectedName' => 'username',
            ],
            'Jira Cloud after 29.04.2019' => [
                'jiraUserData' => self::createCustomObject(
                    [
                        'accountId' => '5cf8e44f98b1560e85999040',
                    ]
                ),
                'expectedName' => '5cf8e44f98b1560e85999040',
            ],
        ];
    }

    /**
     * Test creating new User instance from Jira user data.
     *
     * @param \stdClass $jiraUserData Given Jira User object.
     * @param string    $expectedName Expected user name.
     *
     * @throws \Throwable
     *
     * @dataProvider jiraUserDataProvider
     */
    public function testCreateUserFromObject(\stdClass $jiraUserData, string $expectedName): void
    {
        $user = User::fromStdClass($this->createMock(Client::class), $jiraUserData);

        self::assertEquals($expectedName, $user->getName());
    }
}
