# gadwinn/akismet

PHP 8.3+ client for the [Akismet REST API v1.1](https://akismet.com/developers/). Strictly-typed, zero third-party dependencies.

## Tech Stack

| Requirement | Version |
|---|---|
| PHP | ≥ 8.3 |
| ext-curl | * |
| Akismet API | v1.1 |

## Installation

```bash
composer require gadwinn/akismet
```

## Usage

### Initialisation

```php
use G\Akismet\Akismet;
use G\Akismet\AkismetException;

$akismet = new Akismet(
    apiKey   : 'your-api-key',
    blogUrl  : 'https://your-site.com',
    userAgent: 'MyApp/2.0'           // optional, defaults to 'PHP Akismet Client'
);
```

---

### Key verification

Call once at startup - not before every request.

```php
if (!$akismet->verifyKey()) {
    throw new RuntimeException('Invalid Akismet API key.');
}
```

---

### Comment check

`checkComment()` returns an `AkismetResult`. The `blog` and `api_key` fields are injected automatically.

```php
$result = $akismet->checkComment([
    // Required
    'user_ip'    => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],

    // Strongly recommended
    'referrer'             => $_SERVER['HTTP_REFERER'] ?? '',
    'permalink'            => 'https://your-site.com/posts/42',
    'comment_type'         => 'comment', // comment | forum-post | reply | contact-form | signup | message
    'comment_author'       => 'Jane Doe',
    'comment_author_email' => 'jane@example.com',
    'comment_author_url'   => 'https://janedoe.dev',
    'comment_content'      => 'Great post!',

    // Optional
    'comment_date_gmt'   => gmdate('c'),
    'blog_lang'          => 'en',
    'blog_charset'       => 'UTF-8',
    'user_role'          => 'subscriber', // 'administrator' always returns ham
    'is_test'            => false,
    'honeypot_field_name'=> 'hidden_field',
    'comment_context'    => ['php', 'open-source'],
]);
```

#### `AkismetResult` API

```php
// Spam classification
$result->isSpam();          // bool
$result->isHam();           // bool

// Severity - act on this before generic spam handling
$result->shouldDiscard();   // bool    - blatant spam, safe to delete without queuing
$result->getProTip();       // ?string - raw value ('discard' | null)

// Deferred recheck
$result->shouldRecheck();   // bool
$result->getRecheckAfter(); // ?int    - seconds before rechecking with 'recheck_reason=recheck'

// Raw data
$result->getRawResponse();               // string - raw API body ('true' | 'false')
$result->getHeaders();                   // array<string, string>
$result->getHeader('X-akismet-pro-tip'); // ?string - case-insensitive lookup
```

#### Routing pattern

```php
match (true) {
    $result->shouldDiscard() => deleteComment($id),
    $result->isSpam()        => queueForModeration($id),
    default                  => publishComment($id),
};
```

---

### Reporting missed spam

Use the **same payload** that was sent to `checkComment()`.

```php
$akismet->submitSpam($commentData); // bool
```

### Reporting false positives

```php
$akismet->submitHam($commentData); // bool
```

---

### Error handling

All three methods throw `AkismetException` on network failure, non-200 HTTP status, or invalid API response.

```php
try {
    $result = $akismet->checkComment($commentData);
} catch (AkismetException $e) {
    $e->getMessage();    // string  - human-readable description
    $e->getHttpCode();   // ?int    - HTTP status code returned by the API
    $e->getDebugHelp();  // ?string - value of X-akismet-debug-help header
    $e->getAlertCode();  // ?int    - X-akismet-alert-code (e.g. 10001, 30001)
    $e->getAlertMsg();   // ?string - X-akismet-alert-msg (subscription issue details)

    // Fail open: pass to moderation rather than blocking the user
    queueForModeration($comment);
}
```

#### Alert codes reference

| Code | Meaning |
|---|---|
| 10001 | API key revoked |
| 10002 | API key invalid |
| 10003 | API key not set |
| 30001 | Account is suspended |
| 30002 | Account has reached API quota limit |

See the full list at [akismet.com/developers/detailed-docs/errors](https://akismet.com/developers/detailed-docs/errors/).

## License

MIT, see [LICENSE](LICENSE).
