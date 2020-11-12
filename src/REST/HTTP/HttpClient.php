<?php

declare(strict_types=1);

namespace Mekras\Jira\REST\HTTP;

use Mekras\Jira\REST\Exception;

/**
 * Internal HTTP client.
 *
 * @internal
 */
interface HttpClient
{
    /**
     * Send HTTP request.
     *
     * @param string               $method    HTTP method or special "MULTIPART".
     * @param string               $url       Requested URL
     * @param string               $username  Jira user name for identification.
     * @param string               $secret    Jira user authentication secret.
     * @param array<string, mixed> $arguments Request arguments (method related).
     * @param array<string, mixed> $info      Container for request result info.
     *
     * @return string|null
     *
     * @throws Exception In case of any errors.
     */
    public function request(
        string $method,
        string $url,
        string $username,
        string $secret,
        array $arguments,
        &$info
    ): ?string;

    /**
     * Set request timeout.
     *
     * @param int $seconds Timeout in seconds.
     */
    public function setTimeout(int $seconds): void;
}
