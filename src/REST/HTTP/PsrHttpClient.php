<?php

declare(strict_types=1);

namespace Badoo\Jira\REST\HTTP;

use Badoo\Jira\REST\Exception;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adapter for PSR-18 HTTP clients.
 *
 * @link  https://www.php-fig.org/psr/psr-18/
 * @since x.x
 */
class PsrHttpClient implements HttpClient
{
    /**
     * HTTP client.
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * HTTP request factory.
     *
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * HTTP stream factory.
     *
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * Create adapter for PSR-18 compatible HTTP client.
     *
     * @param ClientInterface         $client         HTTP client.
     * @param RequestFactoryInterface $requestFactory HTTP request factory.
     * @param StreamFactoryInterface  $streamFactory  HTTP stream factory.
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

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
        if ($method === 'MULTIPART') {
            if (!class_exists(MultipartStreamBuilder::class)) {
                throw new Exception(
                    'You should install php-http/multipart-stream-builder to use MULTIPART with PsrHttpClient.'
                );
            }

            $builder = new MultipartStreamBuilder($this->streamFactory);
            foreach ($arguments as $name => $content) {
                $builder->addResource($name, $content);
            }

            $multipartStream = $builder->build();
            $boundary = $builder->getBoundary();

            $request = $this->requestFactory
                ->createRequest('POST', $url)
                ->withHeader('Content-Type', 'multipart/form-data; boundary="' . $boundary . '"')
                ->withHeader('X-Atlassian-Token', 'no-check')
                ->withBody($multipartStream);
        } else {
            $request = $this->requestFactory
                ->createRequest($method, $url)
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json');
            if (in_array($method, ['POST', 'PUT'], true)) {
                $body = $this->streamFactory->createStream((string) json_encode($arguments));
                $request = $request->withBody($body);
            }
        }

        $request = $request
            ->withHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $secret));

        $response = $this->client->sendRequest($request);

        $info = [
            'http_code' => $response->getStatusCode(),
            'content_type' => $response->getHeaderLine('Content-Type'),
        ];

        return (string) $response->getBody();
    }

    /**
     * Set request timeout.
     *
     * @param int $seconds Timeout in seconds.
     */
    public function setTimeout(int $seconds): void
    {
        // Not supported.
    }
}
