<?php

declare(strict_types=1);

namespace G\Akismet;

/**
 * Interface for Akismet spam detection service.
 *
 * Provides methods for key verification, comment checking, and spam reporting
 * based on the Akismet REST API v1.1.
 *
 * @see https://akismet.com/developers/
 */
interface AkismetInterface
{
    /**
     * Verify that the API key is valid.
     *
     * This should be called before using other methods for the first time.
     * You don't need to call this before every request.
     *
     * @return bool True if the key is valid, false otherwise
     * @throws AkismetException When the API request fails
     *
     * @see https://akismet.com/developers/key-verification/
     */
    public function verifyKey(): bool;

    /**
     * Check if submitted content is spam.
     *
     * This is the main method you'll use most often. It analyzes the provided
     * content and returns a result indicating whether it's spam or not.
     *
     * Required parameters in $commentData:
     * - 'blog' (string): The front page URL of your site (must include http://)
     * - 'user_ip' (string): IP address of the content submitter
     * - 'user_agent' (string): User agent string of the submitter's browser
     *
     * Optional but recommended parameters:
     * - 'referrer' (string): HTTP_REFERER header content
     * - 'permalink' (string): Permanent URL of the entry
     * - 'comment_type' (string): Type of content (comment, forum-post, reply, etc.)
     * - 'comment_author' (string): Name of the content author
     * - 'comment_author_email' (string): Email of the content author
     * - 'comment_author_url' (string): URL submitted by the author
     * - 'comment_content' (string): The actual content being checked
     * - 'comment_date_gmt' (string): UTC timestamp in ISO 8601 format
     * - 'comment_post_modified_gmt' (string): UTC timestamp of post publication
     * - 'blog_lang' (string): Site language in ISO 639-1 format (e.g., "en, fr_ca")
     * - 'blog_charset' (string): Character encoding (e.g., "UTF-8")
     * - 'user_role' (string): User role (use "administrator" to always get false)
     * - 'is_test' (bool): Set to true for test queries
     * - 'recheck_reason' (string): Reason for rechecking (e.g., "edit")
     * - 'honeypot_field_name' (string): Name of honeypot field
     * - 'comment_context' (array): Tags/categories of the parent post
     *
     * @param array<string, mixed> $commentData Associative array with comment data and metadata
     * @return AkismetResult Result object containing spam status and additional info
     * @throws AkismetException When the API request fails or data is invalid
     *
     * @see https://akismet.com/developers/comment-check/
     */
    public function checkComment(array $commentData): AkismetResult;

    /**
     * Submit content that should have been marked as spam (missed spam).
     *
     * Use this to report content that Akismet incorrectly classified as ham
     * but was actually spam. This helps improve Akismet's accuracy.
     *
     * Important: The data should match the original comment-check call as closely
     * as possible so Akismet can learn from its mistakes.
     *
     * @param array<string, mixed> $commentData Same data structure as checkComment()
     * @return bool True if the submission was successful
     * @throws AkismetException When the API request fails
     *
     * @see https://akismet.com/developers/submit-spam-missed-spam/
     */
    public function submitSpam(array $commentData): bool;

    /**
     * Submit content that was incorrectly marked as spam (false positive).
     *
     * Use this to report content that Akismet incorrectly classified as spam
     * but was actually legitimate content (ham).
     *
     * Important: The data should match the original comment-check call as closely
     * as possible so Akismet can learn from its mistakes.
     *
     * @param array<string, mixed> $commentData Same data structure as checkComment()
     * @return bool True if the submission was successful
     * @throws AkismetException When the API request fails
     *
     * @see https://akismet.com/developers/submit-ham-false-positives/
     */
    public function submitHam(array $commentData): bool;
}
