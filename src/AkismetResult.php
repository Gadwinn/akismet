<?php

declare(strict_types=1);

namespace G\Akismet;

/**
 * Result object for Akismet comment check operations.
 *
 * Contains the spam detection result and additional metadata from the API response.
 */
class AkismetResult
{
    /**
     * Pro tip value indicating content should be discarded (blatant spam).
     */
    private const PRO_TIP_DISCARD = 'discard';

    /**
     * Whether the content is classified as spam.
     *
     * @var bool
     */
    private bool $is_spam;

    /**
     * Pro tip from Akismet for handling the content.
     *
     * If set to 'discard', the content is blatant spam and can be discarded
     * without saving it to any spam queue.
     *
     * @var string|null
     */
    private ?string $pro_tip = null;

    /**
     * Number of seconds after which to recheck the content.
     *
     * If present, Akismet is requesting that you check the comment again
     * after this many seconds with the additional parameter 'recheck_reason=recheck'.
     *
     * @var int|null
     */
    private ?int $recheck_after = null;

    /**
     * Raw response body from the API.
     *
     * @var string
     */
    private string $raw_response;

    /**
     * Response headers from the API.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Create a new AkismetResult.
     *
     * @param bool $isSpam Whether the content is spam
     * @param string $rawResponse Raw response body from API
     * @param array<string, string> $headers Response headers from API
     */
    public function __construct(bool $isSpam, string $rawResponse, array $headers = [])
    {
        $this->is_spam = $isSpam;
        $this->raw_response = $rawResponse;
        $this->headers = $headers;

        // Parse special headers
        $this->parseHeaders($headers);
    }

    /**
     * Check if the content is classified as spam.
     *
     * @return bool True if spam, false if ham (legitimate content)
     */
    public function isSpam(): bool
    {
        return $this->is_spam;
    }

    /**
     * Check if the content is classified as ham (legitimate content).
     *
     * @return bool True if ham, false if spam
     */
    public function isHam(): bool
    {
        return !$this->is_spam;
    }

    /**
     * Get the pro tip from Akismet.
     *
     * @return string|null Pro tip value ('discard' for blatant spam), or null if not set
     */
    public function getProTip(): ?string
    {
        return $this->pro_tip;
    }

    /**
     * Check if Akismet recommends discarding the content.
     *
     * @return bool True if the content should be discarded (blatant spam)
     */
    public function shouldDiscard(): bool
    {
        return $this->pro_tip === self::PRO_TIP_DISCARD;
    }

    /**
     * Get the number of seconds after which to recheck.
     *
     * @return int|null Number of seconds, or null if no recheck is requested
     */
    public function getRecheckAfter(): ?int
    {
        return $this->recheck_after;
    }

    /**
     * Check if Akismet requests a recheck.
     *
     * @return bool True if a recheck is requested
     */
    public function shouldRecheck(): bool
    {
        return $this->recheck_after !== null;
    }

    /**
     * Get the raw response body from the API.
     *
     * @return string Raw response body
     */
    public function getRawResponse(): string
    {
        return $this->raw_response;
    }

    /**
     * Get all response headers.
     *
     * @return array<string, string> Response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific response header.
     *
     * @param string $name Header name (case-insensitive)
     * @return string|null Header value, or null if not found
     */
    public function getHeader(string $name): ?string
    {
        $lowercaseName = strtolower($name);

        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $lowercaseName) {
                return $headerValue;
            }
        }

        return null;
    }

    /**
     * Parse special headers from the API response.
     *
     * @param array<string, string> $headers Response headers
     */
    private function parseHeaders(array $headers): void
    {
        // Check for pro tip header
        $proTip = $this->getHeaderFromArray($headers, 'X-akismet-pro-tip');
        if ($proTip !== null) {
            $this->pro_tip = $proTip;
        }

        // Check for recheck after header
        $recheckAfter = $this->getHeaderFromArray($headers, 'X-akismet-recheck-after');
        if ($recheckAfter !== null && is_numeric($recheckAfter)) {
            $this->recheck_after = (int) $recheckAfter;
        }
    }

    /**
     * Get a header value from the headers array (case-insensitive).
     *
     * @param array<string, string> $headers Headers array
     * @param string $name Header name
     * @return string|null Header value or null if not found
     */
    private function getHeaderFromArray(array $headers, string $name): ?string
    {
        $lowercaseName = strtolower($name);

        foreach ($headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $lowercaseName) {
                return $headerValue;
            }
        }

        return null;
    }
}
