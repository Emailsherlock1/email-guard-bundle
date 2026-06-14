<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Validator;

use Emailsherlock\EmailGuard\GuardDecisionSource;
use Emailsherlock\EmailGuardBundle\GuardFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class VerifiedEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly GuardFactory $guards,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VerifiedEmail) {
            throw new UnexpectedTypeException($constraint, VerifiedEmail::class);
        }
        // Empty values are NotBlank's job, mirroring the stock Email constraint.
        if ($value === null || $value === '') {
            return;
        }
        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $result = $this->guards
            ->create($constraint->blockOn, $constraint->reviewOn)
            ->check((string) $value);

        if ($result->isDenied()) {
            // Key-independent visibility: log every block so operators can see
            // what the guard filters even without telemetry configured (no API
            // key). Domain only, never the address — same PII rule as the
            // telemetry event. The reporter-based DB telemetry is the richer,
            // key-gated path; this log always fires.
            $this->logger?->info('email_guard.denied', [
                'domain' => $this->domainOf((string) $value),
                'verdict' => $result->verdict->value,
                'reasons' => $result->reasons,
                'source' => $result->apiCalled ? GuardDecisionSource::REMOTE : GuardDecisionSource::LOCAL,
            ]);

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reasons }}', implode(', ', $result->reasons))
                ->setCode(VerifiedEmail::DENIED_ERROR)
                ->addViolation();
        }
    }

    /**
     * Domain part for logging: lowercased, null when there is no @ (syntax
     * failure). A bare domain is not personal data; the local part is, and it
     * never leaves this method.
     */
    private function domainOf(string $address): ?string
    {
        $at = strrpos($address, '@');
        if ($at === false) {
            return null;
        }
        $domain = strtolower(substr(trim($address), $at + 1));

        return $domain === '' ? null : $domain;
    }
}
