# email-guard-bundle

Symfony integration for [Email-Guard](https://github.com/Emailsherlock1/email-guard-spec):
blocks junk email addresses at form submit, before they reach your database.

`deleted+user274@deleted.invalid` passes Symfony's `Email` constraint. So does
every disposable address. This bundle adds the missing layer on top of
[email-guard-core](https://github.com/Emailsherlock1/email-guard-core-php):
syntax profile, reserved TLDs, 73k+ disposable domains, all checked locally
with zero latency. An [EmailSherlock API key](https://emailsherlock.com/api/docs)
adds live MX, SMTP probe, and a fresh disposable list.

## Install

```bash
composer require emailsherlock/email-guard-bundle
```

Symfony Flex registers the bundle; otherwise add `EmailGuardBundle` to
`config/bundles.php`.

## Configure

Everything is optional. Without config the guard runs local checks with the
spec defaults:

```yaml
# config/packages/email_guard.yaml
email_guard:
    api_key: '%env(default::EMAILGUARD_API_KEY)%'   # optional, null = local only
    fail_open: true
    timeout_ms: 800
    block_on: ['invalid', 'disposable']
    review_on: []
```

**Fail-open is the default on purpose.** If the API is unreachable, local
checks keep working and the rest passes. Your signup form never breaks
because of our API.

## Use

As a form option, one line per field:

```php
$builder->add('email', EmailType::class, [
    'verify_email' => true,
]);
```

As a constraint, wherever Symfony validation runs (forms, API DTOs, manual
`validate()`):

```php
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;

#[VerifiedEmail]
private string $email;

#[VerifiedEmail(blockOn: ['invalid', 'disposable', 'role'])]
private string $contactEmail;
```

A denied address produces one violation: *"This email address can't receive
mail. Check it for typos."* Override per constraint via `message:`.

## Review flows

`review_on` verdicts add no violation; validation is allow-or-deny by
design. For a second gate (hold the signup, require confirmation), inject
the guard and read the result yourself:

```php
use Emailsherlock\EmailGuard\EmailGuard;

public function __construct(private EmailGuard $guard) {}

$result = $this->guard->check($email);
if ($result->needsReview()) { ... }
```

## Reacting to decisions

The bundle dispatches a `GuardDecisionEvent` for every decision (allow,
deny, review), so you can react however you like: log to your own channel,
write to your own store, push a metric, fire a webhook. Register a listener:

```php
use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;

#[AsEventListener]
final class MyGuardListener
{
    public function __invoke(GuardDecisionEvent $event): void
    {
        if ($event->result->isDenied()) {
            // $event->domain  -> the domain part (not personal data)
            // $event->input   -> the raw address (PII: your responsibility)
            // $event->result  -> verdict, action, reasons, degraded, ...
        }
    }
}
```

The bundle ships one default listener that logs denies as
`email_guard.denied` (domain only, never the address) via PSR-3, so blocks
are visible even without an API key. It is just one subscriber: to silence
it, override the `email_guard.event.decision_log_listener` service.

For aggregate analytics across your sites, set an API key instead: the guard
then reports decisions to your EmailSherlock account (domain only), no
listener needed.

## Custom transport

Bind your own `TransportInterface` (e.g. `Psr18Transport` around your HTTP
client) by decorating the `email_guard.factory` service arguments. The
default uses ext-curl.

## License

MIT, see [LICENSE](LICENSE).
