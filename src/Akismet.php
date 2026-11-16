<?php

declare(strict_types=1);

namespace G\Akismet;

/**
 * Akismet spam detection service implementation.
 *
 * This class provides a PHP implementation for the Akismet REST API v1.1.
 * It handles communication with Akismet servers for spam detection.
 *
 * @see https://akismet.com/developers/
 */
class Akismet implements AkismetInterface
{
    /**
     * Akismet API base URL.
     */
    private const API_BASE_URL = 'https://rest.akismet.com/1.1/';

    /**
     * Expected API response for a valid key.
     */
    private const RESPONSE_VALID = 'valid';

    /**
     * Expected API response when a comment is spam.
     */
    private const RESPONSE_SPAM = 'true';

    /**
     * Expected API response when a submission (spam or ham) is accepted.
     */
    private const RESPONSE_THANKS = 'Thanks for making the web a better place';

    /**
     * Bodies of a successful answer other than a submission.
     */
    private const KNOWN_RESPONSES = ['true', 'false', 'valid', 'invalid'];

    private const TIMEOUT = 30;
    private const CONNECT_TIMEOUT = 10;

    /**
     * Expected HTTP status code for a successful API response.
     */
    private const HTTP_OK = 200;

    /**
     * API key for Akismet service.
     *
     * @var string
     */
    private string $api_key;

    /**
     * Blog URL for API requests.
     *
     * @var string
     */
    private string $blog_url;

    /**
     * User agent string for API requests.
     *
     * @var non-empty-string
     */
    private string $user_agent;

    /**
     * Create a new Akismet instance.
     *
     * @param string $apiKey Your Akismet API key
     * @param string $blogUrl The front page URL of your site (must include http://)
     * @param non-empty-string $userAgent User agent string for your application
     */
    public function __construct(string $apiKey, string $blogUrl, string $userAgent = 'PHP Akismet Client')
    {
        $this->api_key = $apiKey;
        $this->blog_url = $blogUrl;
        $this->user_agent = $userAgent;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyKey(): bool
    {
        $data = [
            'api_key' => $this->api_key,
            'blog' => $this->blog_url,
        ];

        $response = $this->makeRequest('verify-key', $data);

        return trim($response['body']) === self::RESPONSE_VALID;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $commentData
     */
    public function checkComment(array $commentData): AkismetResult
    {
        // Add required API parameters
        $data = array_merge($commentData, [
            'api_key' => $this->api_key,
            'blog' => $this->blog_url,
        ]);

        $response = $this->makeRequest('comment-check', $data);

        $isSpam = trim($response['body']) === self::RESPONSE_SPAM;

        return new AkismetResult($isSpam, $response['body'], $response['headers']);
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $commentData
     */
    public function submitSpam(array $commentData): bool
    {
        // Add required API parameters
        $data = array_merge($commentData, [
            'api_key' => $this->api_key,
            'blog' => $this->blog_url,
        ]);

        $response = $this->makeRequest('submit-spam', $data);

        return str_contains($response['body'], self::RESPONSE_THANKS);
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $commentData
     */
    public function submitHam(array $commentData): bool
    {
        // Add required API parameters
        $data = array_merge($commentData, [
            'api_key' => $this->api_key,
            'blog' => $this->blog_url,
        ]);

        $response = $this->makeRequest('submit-ham', $data);

        return str_contains($response['body'], self::RESPONSE_THANKS);
    }

    /**
     * Call an Akismet endpoint and check its answer.
     *
     * The API key travels in the request body (`api_key`), as documented by Akismet.
     *
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data POST data
     * @return array{body: string, headers: array<string, string>} Response with 'body' and 'headers' keys
     * @throws AkismetException When the request fails
     */
    protected function makeRequest(string $endpoint, array $data): array
    {
        $response = $this->send(self::API_BASE_URL . $endpoint, http_build_query($data));

        $httpCode = $response['status'];
        $body = $response['body'];
        $headers = $this->parseHeaders($response['headers']);

        // Subscription alert headers may accompany any response
        $alertCode = isset($headers['x-akismet-alert-code']) ? (int) $headers['x-akismet-alert-code'] : null;
        $alertMsg = $headers['x-akismet-alert-msg'] ?? null;
        $debugHelp = $headers['x-akismet-debug-help'] ?? null;

        if ($httpCode !== self::HTTP_OK) {
            throw new AkismetException(
                'API request failed with HTTP ' . $httpCode,
                $httpCode,
                null,
                $debugHelp,
                $httpCode,
                $alertCode,
                $alertMsg
            );
        }

        // Akismet reports its own errors with a 200 status: only the body tells
        if (!in_array(trim($body), self::KNOWN_RESPONSES, true) && !str_contains($body, self::RESPONSE_THANKS)) {
            throw new AkismetException(
                'Invalid API response: ' . $body,
                0,
                null,
                $debugHelp,
                $httpCode,
                $alertCode,
                $alertMsg
            );
        }

        return [
            'body' => $body,
            'headers' => $headers,
        ];
    }

    /**
     * Send the HTTP POST request (the only method touching the network).
     *
     * @param non-empty-string $url
     * @param string $postData URL-encoded body
     * @return array{status: int, headers: string, body: string} Status code, raw header block and body
     * @throws AkismetException When the request cannot be completed
     */
    protected function send(string $url, string $postData): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new AkismetException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_USERAGENT => $this->user_agent,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if (!is_string($response)) {
            throw new AkismetException('cURL error: ' . curl_error($ch));
        }

        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        return [
            'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    /**
     * Parse HTTP headers from response string.
     *
     * @param string $headerString Raw header string
     * @return array<string, string> Parsed headers as key-value pairs
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }
}
