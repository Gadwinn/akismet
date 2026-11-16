<?php

declare(strict_types=1);

namespace G\Akismet\Tests;

use G\Akismet\Akismet;
use G\Akismet\AkismetResult;
use G\Akismet\AkismetException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AkismetTest extends TestCase
{
    private Akismet $akismet;
    private string $test_api_key = 'test123';
    private string $test_blog_url = 'https://example.com';
    private string $test_user_agent = 'Test Akismet Client';

    protected function setUp(): void
    {
        $this->akismet = new Akismet(
            $this->test_api_key,
            $this->test_blog_url,
            $this->test_user_agent
        );
    }

    public function testConstructor(): void
    {
        $akismet = new Akismet('api123', 'https://test.com', 'Custom Agent');
        $this->assertInstanceOf(Akismet::class, $akismet);
    }

    public function testConstructorWithDefaultUserAgent(): void
    {
        $akismet = new Akismet('api123', 'https://test.com');
        $this->assertInstanceOf(Akismet::class, $akismet);
    }

    public function testVerifyKeyValid(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'valid',
                'headers' => []
            ]);

        $result = $akismet->verifyKey();
        $this->assertTrue($result);
    }

    public function testVerifyKeyInvalid(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'invalid',
                'headers' => []
            ]);

        $result = $akismet->verifyKey();
        $this->assertFalse($result);
    }

    public function testCheckCommentSpam(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'true',
                'headers' => ['x-akismet-pro-tip' => 'discard']
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_author' => 'John Doe',
            'comment_author_email' => 'john@example.com',
            'comment_content' => 'This is spam content',
            'user_ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0'
        ];

        $result = $akismet->checkComment($commentData);
        
        $this->assertInstanceOf(AkismetResult::class, $result);
        $this->assertTrue($result->isSpam());
        $this->assertEquals('true', $result->getRawResponse());
        $this->assertArrayHasKey('x-akismet-pro-tip', $result->getHeaders());
    }

    public function testCheckCommentHam(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'false',
                'headers' => []
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_author' => 'Jane Doe',
            'comment_author_email' => 'jane@example.com',
            'comment_content' => 'This is legitimate content',
            'user_ip' => '192.168.1.2',
            'user_agent' => 'Mozilla/5.0'
        ];

        $result = $akismet->checkComment($commentData);
        
        $this->assertInstanceOf(AkismetResult::class, $result);
        $this->assertFalse($result->isSpam());
        $this->assertEquals('false', $result->getRawResponse());
    }

    public function testSubmitSpamSuccess(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'Thanks for making the web a better place.',
                'headers' => []
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_author' => 'Spammer',
            'comment_content' => 'Spam content',
            'user_ip' => '192.168.1.3',
            'user_agent' => 'Bot'
        ];

        $result = $akismet->submitSpam($commentData);
        $this->assertTrue($result);
    }

    public function testSubmitSpamFailure(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'Error occurred',
                'headers' => []
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_content' => 'Test content'
        ];

        $result = $akismet->submitSpam($commentData);
        $this->assertFalse($result);
    }

    public function testSubmitHamSuccess(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'Thanks for making the web a better place.',
                'headers' => []
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_author' => 'Legitimate User',
            'comment_content' => 'Good content',
            'user_ip' => '192.168.1.4',
            'user_agent' => 'Browser'
        ];

        $result = $akismet->submitHam($commentData);
        $this->assertTrue($result);
    }

    public function testSubmitHamFailure(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => 'Error occurred',
                'headers' => []
            ]);

        $commentData = [
            'comment_type' => 'comment',
            'comment_content' => 'Test content'
        ];

        $result = $akismet->submitHam($commentData);
        $this->assertFalse($result);
    }

    public function testMakeRequestThrowsExceptionOnCurlError(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willThrowException(new AkismetException('cURL error: Could not connect'));

        $this->expectException(AkismetException::class);
        $this->expectExceptionMessage('cURL error:');

        $akismet->verifyKey();
    }

    public function testMakeRequestThrowsExceptionOnHttpError(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willThrowException(new AkismetException('API request failed with HTTP 400', 400));

        $this->expectException(AkismetException::class);
        $this->expectExceptionMessage('API request failed with HTTP 400');

        $akismet->verifyKey();
    }

    public function testMakeRequestThrowsExceptionOnInvalidResponse(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willThrowException(new AkismetException('Invalid API response: unexpected'));

        $this->expectException(AkismetException::class);
        $this->expectExceptionMessage('Invalid API response:');

        $akismet->verifyKey();
    }

    public function testParseHeadersReturnsCorrectArray(): void
    {
        $reflection = new \ReflectionClass($this->akismet);
        $method = $reflection->getMethod('parseHeaders');

        $headerString = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nX-Akismet-Pro-Tip: discard\r\n\r\n";
        $headers = $method->invoke($this->akismet, $headerString);

        $this->assertIsArray($headers);
        $this->assertEquals('text/plain', $headers['content-type']);
        $this->assertEquals('discard', $headers['x-akismet-pro-tip']);
    }

    public function testParseHeadersHandlesEmptyString(): void
    {
        $reflection = new \ReflectionClass($this->akismet);
        $method = $reflection->getMethod('parseHeaders');

        $headers = $method->invoke($this->akismet, '');
        $this->assertIsArray($headers);
        $this->assertEmpty($headers);
    }

    public function testVerifyKeyWithWhitespaceInResponse(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willReturn([
                'body' => '  valid  ',
                'headers' => []
            ]);

        $result = $akismet->verifyKey();
        $this->assertTrue($result);
    }

    public function testAkismetExceptionStoresAlertCodeAndMsg(): void
    {
        $exception = new AkismetException('Subscription suspended', 0, null, null, 200, 10006, 'Subscription suspended due to improper use.');

        $this->assertEquals(10006, $exception->getAlertCode());
        $this->assertEquals('Subscription suspended due to improper use.', $exception->getAlertMsg());
    }

    public function testAkismetExceptionNullAlertsByDefault(): void
    {
        $exception = new AkismetException('Some error');

        $this->assertNull($exception->getAlertCode());
        $this->assertNull($exception->getAlertMsg());
    }

    public function testMakeRequestPropagatesAlertCodeOnHttpError(): void
    {
        $akismet = $this->createMockAkismet();
        $akismet->method('makeRequest')
            ->willThrowException(
                new AkismetException('API request failed with HTTP 400', 400, null, null, 400, 10403, 'Access revoked.')
            );

        try {
            $akismet->verifyKey();
            $this->fail('Expected AkismetException was not thrown');
        } catch (AkismetException $e) {
            $this->assertEquals(10403, $e->getAlertCode());
            $this->assertEquals('Access revoked.', $e->getAlertMsg());
        }
    }

    /**
     * Create a mock Akismet instance for testing.
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject|Akismet
     */
    private function createMockAkismet()
    {
        return $this->getMockBuilder(Akismet::class)
            ->setConstructorArgs([$this->test_api_key, $this->test_blog_url, $this->test_user_agent])
            ->onlyMethods(['makeRequest'])
            ->getMock();
    }
}
