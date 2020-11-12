<?php
/**
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST;

use Mekras\Jira\REST\HTTP\CurlClient;
use Mekras\Jira\REST\HTTP\HttpClient;
use Mekras\Jira\Cache\NullCache;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Raw client to JIRA REST API.
 *
 * Provides the most direct access to API possible, without any bindings. Supports authorization,
 * allows to just send HTTP requests to API and get responses parsed as JSON or raw response data
 * when needed.
 *
 * Treats API error responses and throws exceptions. That's all.
 */
class ClientRaw
{
    /**
     * TODO Describe this.
     *
     * @var string
     */
    private $apiPrefix;

    /**
     * Cache for HTTP responses.
     *
     * @var CacheInterface
     */
    private $httpCache;

    /**
     * HTTP client.
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * TODO Describe this.
     *
     * @var string
     */
    private $jiraUrl;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * User name to use in API requests.
     *
     * @var string
     */
    private $login = '';

    /**
     * TODO Describe this.
     *
     * @var int
     */
    private $requestTimeout = 60;

    /**
     * Authentication secret. It can be API token (good) or bare user password (deprecated).
     *
     * @var string
     */
    private $secret = '';

    /**
     * Create client.
     *
     * @param string               $jiraUrl    Jira instance root URL, e. g.
     *                                         "https://jira.localhost/".
     * @param string               $apiPrefix  Jira API relative URL, e. g. "/rest/api/latest/".
     * @param HttpClient           $httpClient HTTP client.
     * @param CacheInterface       $cache      Cache for HTTP responses.
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $jiraUrl,
        string $apiPrefix,
        HttpClient $httpClient,
        CacheInterface $cache,
        LoggerInterface $logger = null
    ) {
        $this->jiraUrl = rtrim($jiraUrl, '/') . '/';
        $this->apiPrefix = trim($apiPrefix, '/') . '/';
        $this->httpClient = $httpClient;
        $this->httpCache = $cache;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Request Jira REST API with HTTP DELETE request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body
     *                           string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Mekras\Jira\REST\Exception
     */
    public function delete(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request('DELETE', $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP GET request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body
     *                           string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Mekras\Jira\REST\Exception
     */
    public function get(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request('GET', $api_method, $arguments, $raw);
    }

    /**
     * Jira URL is a URL to root Jira Web UI (it does not contain API path)
     *
     * Jira URL always ends with '/' character.
     */
    public function getJiraUrl(): string
    {
        return $this->jiraUrl;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Request Jira REST API with HTTP POST request type and multipart request body encoding.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body
     *                           string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Mekras\Jira\REST\Exception
     */
    public function multipart(string $api_method, array $arguments, bool $raw = false)
    {
        return $this->request('MULTIPART', $api_method, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP POST request type.
     *
     * @param string $apiMethod API method path (e.g. issue/<key>).
     * @param mixed  $arguments Request data (parameters).
     * @param bool   $raw       Don't parse response as JSON, just return raw response body string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Mekras\Jira\REST\Exception
     */
    public function post(string $apiMethod, $arguments = [], bool $raw = false)
    {
        return $this->request('POST', $apiMethod, $arguments, $raw);
    }

    /**
     * Request Jira REST API with HTTP PUT request type.
     *
     * @param string $api_method - API method path (e.g. issue/<key>)
     * @param array  $arguments  - request data (parameters)
     * @param bool   $raw        - don't parse response as JSON, just return raw response body
     *                           string.
     *
     * @return string|\stdClass|\stdClass[]|null
     * @throws \Mekras\Jira\REST\Exception
     */
    public function put(string $api_method, array $arguments = [], bool $raw = false)
    {
        return $this->request('PUT', $api_method, $arguments, $raw);
    }

    /**
     * Set credentials to use in each request to Jira REST API.
     *
     * @param string $login  - user login
     * @param string $secret - raw user password (deprecated) or API token (good)
     *
     * @return ClientRaw
     */
    public function setAuth(string $login, string $secret): ClientRaw
    {
        $this->login = $login;
        $this->secret = $secret;

        return $this;
    }

    public function setRequestTimeout(int $requestTimeout): ClientRaw
    {
        $this->requestTimeout = $requestTimeout;

        return $this;
    }

    private function handleAPIError(
        int $http_code,
        string $content_type,
        string $response_raw,
        $response
    ): void {
        $is_html = strpos($content_type, 'text/html') === 0;
        $is_json = strpos($content_type, 'application/json') === 0;

        if ($http_code === 401 && $is_html) {
            throw new \Mekras\Jira\REST\Exception\Authorization(
                "Jira API authorization failed, please check credentials"
            );
        }

        if ($http_code === 403 && $is_html) {
            throw new \Mekras\Jira\REST\Exception\Authorization(
                "Access to the API method is forbidden. You either have not enough privileges or the capcha shown to your user"
            );
        }

        if ($http_code >= 400 && !$is_json) {
            throw new \Mekras\Jira\REST\Exception(
                "Jira REST API responded with code {$http_code} and content type {$content_type}. API answer: " . var_export(
                    $response_raw,
                    1
                )
            );
        }

        if (!$is_json) {
            return;
        }

        if (!empty($response->errorMessages)) {
            $Error = new \Mekras\Jira\REST\Exception(
                "Jira REST API call error: " . implode('; ', $response->errorMessages)
            );
            $Error->setApiResponse($response);
            throw $Error;
        }

        if ($http_code >= 400) {
            $Error = new \Mekras\Jira\REST\Exception($this->renderExceptionMessage($response));
            $Error->setApiResponse($response);

            throw $Error;
        }
    }

    private function renderExceptionMessage(\stdClass $ErrorResponse): string
    {
        if (!empty($ErrorResponse->message)) {
            return "Jira REST API returned an error: " . $ErrorResponse->message;
        }

        $errors = array_merge(
            (array) $ErrorResponse->errorMessages ?? [],
            (array) $ErrorResponse->errors ?? []
        );

        return "Jira REST API returned an error:\n\t" . implode("\n\t", $errors);
    }

    /**
     * Make a request to Jira REST API and parse response.
     * Return array with response data parsed as JSON or null for empty response body.
     *
     * @param string $httpMethod  - HTTP request method (e.g. HEAD/PUT/GET...)
     * @param string $api_method  - API method path (e.g. issue/<key>)
     * @param mixed  $arguments   - request data (parameters)
     * @param bool   $raw         - don't parse response as JSON, just return raw response body
     *                            string.
     *
     * @return string|\stdClass|\stdClass[]|null - raw response (string), response parsed as JSON
     *                                           (array, \stdClass) or null for responses with
     *                                           empty body
     *
     * @throws \Mekras\Jira\REST\Exception - on JSON parse errors, on warning HTTP codes and other
     *                                    errors.
     */
    private function request(
        string $httpMethod,
        string $api_method,
        $arguments = [],
        bool $raw = false
    ) {
        $url = $this->getJiraUrl() . $this->apiPrefix . ltrim($api_method, '/');
        if (in_array($httpMethod, ['GET', 'DELETE']) && !empty($arguments)) {
            $url .= '?' . http_build_query($arguments);
        }

        $cacheKey = in_array($httpMethod, ['GET'/* , TODO ??? */], true)
            ? sha1($httpMethod . $url)
            : null;

        $this->logger->debug(sprintf('Method "%s %s" requested.', $httpMethod, $url));
        if ($cacheKey && $this->httpCache->has($cacheKey)) {
            $this->logger->debug('Using cached response.');
            $cached = $this->httpCache->get($cacheKey);
            $resultRaw = $cached['body'];
            $httpCode = $cached['http_code'];
            $contentType = $cached['content_type'];
            $is_success = true;
        } else {
            $this->logger->debug('Sending request to Jira API…');
            $resultRaw = $this->httpClient->request(
                $httpMethod,
                $url,
                $this->login,
                $this->secret,
                $arguments,
                $info
            );

            $httpCode = (int) $info['http_code'];
            $contentType = $info['content_type'];

            $is_success = in_array($httpCode, [200, 201, 204], true);

            if ($cacheKey !== null && $is_success) {
                $this->httpCache->set(
                    $cacheKey,
                    [
                        'body' => $resultRaw,
                        'content_type' => $contentType,
                        'http_code' => $httpCode,
                    ]
                );
            }
        }

        if ($is_success && (string) $resultRaw === '') {
            return null; // empty response body is OK of some API methods
        }

        $result = json_decode($resultRaw);
        $error = json_last_error();
        $json_error = $error !== JSON_ERROR_NONE;

        $is_json = strpos($contentType, 'application/json') === 0;
        if ($is_json && $json_error) {
            throw new \Mekras\Jira\REST\Exception(
                "Jira REST API interaction error, failed to parse JSON: " . json_last_error_msg()
                . ". Raw API response: " . var_export($resultRaw, 1)
            );
        }

        $this->handleAPIError($httpCode, $contentType, $resultRaw, $result);

        if ($raw) {
            return $resultRaw;
        }

        if ($json_error) {
            throw new \Mekras\Jira\REST\Exception(
                "Jira REST API responded with non-JSON data. " .
                "Use <raw> parameter if you want to get the result as a string"
            );
        }

        return $result;
    }
}
