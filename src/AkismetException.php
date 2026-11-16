<?php

declare(strict_types=1);

namespace G\Akismet;

use Exception;
use Throwable;

/**
 * Exception thrown when Akismet API operations fail.
 *
 * This exception is thrown when there are issues with API requests,
 * invalid responses, network problems, or API errors.
 */
class AkismetException extends Exception
{
    /**
     * Optional debug information from Akismet API headers.
     *
     * @var string|null
     */
    private ?string $debug_help = null;

    /**
     * HTTP response code from the API.
     *
     * @var int|null
     */
    private ?int $http_code = null;

    /**
     * Akismet subscription alert code from X-akismet-alert-code header.
     *
     * @var int|null
     */
    private ?int $alert_code = null;

    /**
     * Akismet subscription alert message from X-akismet-alert-msg header.
     *
     * @var string|null
     */
    private ?string $alert_msg = null;

    /**
     * Create a new AkismetException.
     *
     * @param string $message The error message
     * @param int $code The error code (default: 0)
     * @param Throwable|null $previous Previous exception for chaining
     * @param string|null $debugHelp Debug information from X-akismet-debug-help header
     * @param int|null $httpCode HTTP response code from the API
     * @param int|null $alertCode Akismet subscription alert code
     * @param string|null $alertMsg Akismet subscription alert message
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?string $debugHelp = null,
        ?int $httpCode = null,
        ?int $alertCode = null,
        ?string $alertMsg = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->debug_help = $debugHelp;
        $this->http_code = $httpCode;
        $this->alert_code = $alertCode;
        $this->alert_msg = $alertMsg;
    }

    /**
     * Get debug information from Akismet API.
     *
     * @return string|null Debug help information, if available
     */
    public function getDebugHelp(): ?string
    {
        return $this->debug_help;
    }

    /**
     * Get HTTP response code from the API.
     *
     * @return int|null HTTP response code, if available
     */
    public function getHttpCode(): ?int
    {
        return $this->http_code;
    }

    /**
     * Get the Akismet subscription alert code.
     *
     * @see https://akismet.com/developers/detailed-docs/errors/
     * @return int|null Alert code (e.g. 10001, 10006, 30001), or null if not set
     */
    public function getAlertCode(): ?int
    {
        return $this->alert_code;
    }

    /**
     * Get the Akismet subscription alert message.
     *
     * @return string|null Human-readable alert message, or null if not set
     */
    public function getAlertMsg(): ?string
    {
        return $this->alert_msg;
    }
}
