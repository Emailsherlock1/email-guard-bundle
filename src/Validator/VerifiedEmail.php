<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Denies addresses the guard can prove are junk: broken syntax, reserved
 * TLDs, disposable providers, and (with an API key) undeliverable mailboxes.
 *
 *     #[VerifiedEmail]
 *     #[VerifiedEmail(blockOn: ['invalid', 'disposable', 'role'])]
 *
 * Runs wherever Symfony validation runs: forms, API DTOs, manual validate().
 * A guard action of `review` adds no violation; review flows read the guard
 * result directly instead of going through validation.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class VerifiedEmail extends Constraint
{
    public const DENIED_ERROR = '3f1d2c7a-9b4e-4d6f-8a2c-5e7b0d9f1a3c';

    protected const ERROR_NAMES = [
        self::DENIED_ERROR => 'DENIED_ERROR',
    ];

    public string $message = "This email address can't receive mail. Check it for typos.";

    /** @var list<string>|null null = bundle defaults */
    public ?array $blockOn = null;

    /** @var list<string>|null null = bundle defaults */
    public ?array $reviewOn = null;

    /**
     * @param list<string>|null $blockOn verdicts that deny; null keeps the bundle defaults
     * @param list<string>|null $reviewOn verdicts that flag for review; null keeps the bundle defaults
     * @param string[]|null $groups
     */
    public function __construct(
        ?array $blockOn = null,
        ?array $reviewOn = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->blockOn = $blockOn;
        $this->reviewOn = $reviewOn;
        $this->message = $message ?? $this->message;
    }
}
