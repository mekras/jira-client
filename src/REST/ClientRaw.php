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
     * @deprecated Will be changed to private in 2.0.
     */
    public const DEFAULT_JIRA_URL = 'https://jira.localhost/';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be changed to private in 2.0.
     */
    public const DEFAULT_JIRA_API_PREFIX = '/rest/api/latest/';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be changed to private in 2.0.
     */
    public const REQ_GET = 'GET';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be deleted in 2.0.
     */
    public const REQ_POST = 'POST';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be changed to private in 2.0.
     */
    public const REQ_PUT = 'PUT';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be changed to private in 2.0.
     */
    public const REQ_DELETE = 'DELETE';

    /**
     * TODO Describe this.
     *
     * @deprecated Will be changed to private in 2.0.
     */
    public const REQ_MULTIPART = 'MULTIPART';

    protected static $instance;

    protected $jira_url;

    protected $api_prefix;

    /**
     * User name to use in API requests.
     *
     * @var string
     */
    private $login = '';

    /**
     * Authentication secret. It can be API token (good) or bare user password (deprecated).
     *
     * @var string
     */
    private $secret = '';

    protected $request_timeout = 60;

    /**
     * HTTP client.
     *
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Cache for HTTP responses.
     *
     * @var CacheInterface
     */
    private $requestCache;

    /**
     * Return singleton instance.
     *
     * @return ClientRaw
     *
     * @deprecated Will be removed in 2.0.
     */
    public static function instance(): ClientRaw
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Create client.
     *
     * @param string               $jiraUrl   TODO Describe.
     * @param string               $apiPrefix TODO Describe.
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        $jiraUrl = self::DEFAULT_JIRA_URL,
        $apiPrefix = self::DEFAULT_JIRA_API_PREFIX,
        LoggerInterface $logger = null
    ) {
        $this->setJiraUrl($jiraUrl);
        $this->setApiPrefix($apiPrefix);
        $this->logger = $logger ?: new NullLogger();
        $this->httpClient = new CurlClient();
        $this->requestCache = new NullCache();
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

    public function getLogin(): string
    {
        return $this->login;
    }

    /**
     * Jira URL is a URL to root Jira Web UI (it does not contain API path)
     *
     * Jira URL always ends with '/' character.
     */
    public function getJiraUrl(): string
    {
        return $this->jira_url;
    }

    public function setJiraUrl(string $url): ClientRaw
    {
        // force URL to end with '/': e.g. 'https://jira.example.com/'
        $this->jira_url = rtrim($url, '/') . '/';

        return $this;
    }

    /**
     * API prefix is a URI that points to Jira API root and is added to each API method request.
     * It usually looks like /rest/api/v2/ or /rest/api/latest/.
     *
     * API prefix always ends with '/' character.
     */
    public function getApiPrefix(): string
    {
        return $this->api_prefix;
    }

    public function setApiPrefix(string $api_prefix): ClientRaw
    {
        // force URI to have no '/' at the beginning and to HAVE '/' at the end: e.g. 'rest/api/latest/'
        $this->api_prefix = trim($api_prefix, '/') . '/';

        return $this;
    }

    public function getRequestTimeout(): int
    {
        return $this->request_timeout;
    }

    public function setRequestTimeout(int $request_timeout): ClientRaw
    {
        $this->request_timeout = $request_timeout;

        return $this;
    }

    //
    // END - API client settings
    //

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
        return $this->request(self::REQ_GET, $api_method, $arguments, $raw);
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
        return $this->request(self::REQ_MULTIPART, $api_method, $arguments, $raw);
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
        return $this->request(self::REQ_PUT, $api_method, $arguments, $raw);
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
        return $this->request(self::REQ_DELETE, $api_method, $arguments, $raw);
    }

    /**
     * Set client for HTTP requests.
     *
     * @param HttpClient $httpClient
     */
    public function setHttpClient(HttpClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Set cache for HTTP requests results.
     *
     * @param CacheInterface $cache
     *
     * @return $this
     *
     * @since 1.3
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->requestCache = $cache;

        return $this;
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
        $url = $this->getJiraUrl() . $this->getApiPrefix() . ltrim($api_method, '/');
        if (in_array($httpMethod, [self::REQ_GET, self::REQ_DELETE]) && !empty($arguments)) {
            $url .= '?' . http_build_query($arguments);
        }

        $cacheKey = in_array($httpMethod, [self::REQ_GET/* , TODO ??? */], true)
            ? sha1($httpMethod . $url)
            : null;

        $this->logger->debug(sprintf('Method "%s %s" requested.', $httpMethod, $url));
        if ($cacheKey && $this->requestCache->has($cacheKey)) {
            $this->logger->debug('Using cached response.');
            $cached = $this->requestCache->get($cacheKey);
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
                $this->requestCache->set(
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

    protected function renderExceptionMessage(\stdClass $ErrorResponse): string
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

    protected function handleAPIError(
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
}
