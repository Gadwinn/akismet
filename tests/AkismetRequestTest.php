<?php

declare(strict_types=1);

namespace G\Akismet\Tests;

use G\Akismet\Akismet;
use G\Akismet\AkismetException;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the whole request pipeline with a canned HTTP answer:
 * only the network call itself is replaced.
 */
final class AkismetRequestTest extends TestCase
{
    /** @var list<array{url: string, data: array<int|string, mixed>}> */
    private array $requests = [];

    private function client(int $status, string $body, string $headers = "HTTP/1.1 200 OK\r\n\r\n"): Akismet
    {
        $record = function (string $url, string $postData): void {
            parse_str($postData, $data);
            $this->requests[] = ['url' => $url, 'data' => $data];
        };

        return new class ('key123', 'https://example.com', 'Test Agent', $status, $headers, $body, $record) extends Akismet {
            public function __construct(
                string $apiKey,
                string $blogUrl,
                string $userAgent,
                private readonly int $status,
                private readonly string $headers,
                private readonly string $body,
                private readonly \Closure $record,
            ) {
                parent::__construct($apiKey, $blogUrl, $userAgent);
            }

            protected function send(string $url, string $postData): array
            {
                ($this->record)($url, $postData);

                return ['status' => $this->status, 'headers' => $this->headers, 'body' => $this->body];
            }
        };
    }

    public function testEveryEndpointUsesTheRestHostAndSendsTheKeyInTheBody(): void
    {
        $this->client(200, 'valid')->verifyKey();
        $this->client(200, 'false')->checkComment(['comment_content' => 'Hi', 'user_ip' => '1.2.3.4']);
        $this->client(200, 'Thanks for making the web a better place.')->submitSpam(['comment_content' => 'x']);
        $this->client(200, 'Thanks for making the web a better place.')->submitHam(['comment_content' => 'x']);

        self::assertSame(
            [
                'https://rest.akismet.com/1.1/verify-key',
                'https://rest.akismet.com/1.1/comment-check',
                'https://rest.akismet.com/1.1/submit-spam',
                'https://rest.akismet.com/1.1/submit-ham',
            ],
            array_column($this->requests, 'url')
        );

        foreach ($this->requests as $request) {
            self::assertSame('key123', $request['data']['api_key']);
            self::assertSame('https://example.com', $request['data']['blog']);
        }

        self::assertSame('Hi', $this->requests[1]['data']['comment_content']);
        self::assertSame('1.2.3.4', $this->requests[1]['data']['user_ip']);
    }

    public function testCallerCannotOverrideTheKeyOrTheBlog(): void
    {
        $this->client(200, 'false')->checkComment(['api_key' => 'other', 'blog' => 'https://evil.test']);

        self::assertSame('key123', $this->requests[0]['data']['api_key']);
        self::assertSame('https://example.com', $this->requests[0]['data']['blog']);
    }

    public function testResponseHeadersReachTheResult(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nX-Akismet-Pro-Tip: discard\r\nContent-Type: text/plain\r\n\r\n";

        $result = $this->client(200, "true\n", $headers)->checkComment([]);

        self::assertTrue($result->isSpam());
        self::assertTrue($result->shouldDiscard());
        self::assertSame('text/plain', $result->getHeader('Content-Type'));
    }

    public function testRecheckHeaderIsParsed(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nX-akismet-recheck-after: 120\r\n\r\n";

        $result = $this->client(200, 'false', $headers)->checkComment([]);

        self::assertTrue($result->isHam());
        self::assertTrue($result->shouldRecheck());
        self::assertSame(120, $result->getRecheckAfter());
    }

    public function testSubmissionSucceedsOnlyOnTheThanksMessage(): void
    {
        self::assertTrue($this->client(200, 'Thanks for making the web a better place.')->submitSpam([]));
        self::assertFalse($this->client(200, 'false')->submitHam([]));
    }

    public function testHttpErrorCarriesStatusDebugHelpAndAlert(): void
    {
        $headers = "HTTP/1.1 403 Forbidden\r\nX-akismet-debug-help: Invalid key\r\n"
            . "X-akismet-alert-code: 10006\r\nX-akismet-alert-msg: Subscription suspended\r\n\r\n";

        try {
            $this->client(403, 'Forbidden', $headers)->checkComment([]);
            self::fail('Expected an AkismetException.');
        } catch (AkismetException $exception) {
            self::assertSame(403, $exception->getHttpCode());
            self::assertSame(403, $exception->getCode());
            self::assertSame('Invalid key', $exception->getDebugHelp());
            self::assertSame(10006, $exception->getAlertCode());
            self::assertSame('Subscription suspended', $exception->getAlertMsg());
        }
    }

    public function testErrorReportedWithStatus200IsAnException(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nX-akismet-debug-help: Empty \"blog\" value\r\n\r\n";

        try {
            $this->client(200, 'Missing required field: blog.', $headers)->checkComment([]);
            self::fail('Expected an AkismetException.');
        } catch (AkismetException $exception) {
            self::assertStringContainsString('Missing required field', $exception->getMessage());
            self::assertSame('Empty "blog" value', $exception->getDebugHelp());
            self::assertSame(200, $exception->getHttpCode());
        }
    }

    public function testPreviousThrowableIsKept(): void
    {
        $previous = new \Error('low level');

        self::assertSame($previous, (new AkismetException('failed', 0, $previous))->getPrevious());
    }
}
