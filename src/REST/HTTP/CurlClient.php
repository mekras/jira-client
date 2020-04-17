<?php

declare(strict_types=1);

namespace Badoo\Jira\REST\HTTP;

use Badoo\Jira\REST\Exception;

/**
 * Builtin internal HTTP client based on cURL.
 *
 * @internal
 */
class CurlClient implements HttpClient
{
    /**
     * Request timeout.
     *
     * @var int Seconds.
     */
    private $timeout = 60;

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
        $arguments,
        &$info
    ): ?string {
        $curl_options = [
            CURLOPT_USERPWD => $username . ':' . $secret,
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        $header_options = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        switch ($method) {
            case 'POST':
                $arguments = json_encode($arguments);
                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = $arguments;
                break;

            case 'MULTIPART':
                $header_options['Content-Type'] = 'multipart/form-data';
                $header_options['X-Atlassian-Token'] = 'no-check';

                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = $arguments;
                break;

            case 'PUT':
                $arguments = json_encode($arguments);
                $curl_options[CURLOPT_CUSTOMREQUEST] = $method;
                $curl_options[CURLOPT_POST] = true;
                $curl_options[CURLOPT_POSTFIELDS] = $arguments;
                break;

            case 'DELETE':
                $curl_options[CURLOPT_CUSTOMREQUEST] = $method;
                break;

            default:
        }

        $headers = [];
        foreach ($header_options as $opt_name => $opt_value) {
            $headers[] = "$opt_name: $opt_value";
        }
        $curl_options[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);

        $result_raw = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($result_raw === false) {
            throw new Exception("Request to '{$url}' timeouted after {$this->timeout} seconds.");
        }

        return $result_raw;
    }

    /**
     * Set request timeout.
     *
     * @param int $seconds Timeout in seconds.
     */
    public function setTimeout(int $seconds): void
    {
        assert($seconds >= 0, 'Timeout should be non-negative.');
        $this->timeout = $seconds;
    }
}
