<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Validator;

use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;
use Emailsherlock\EmailGuardBundle\GuardFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class VerifiedEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly GuardFactory $guards,
        private readonly ?EventDispatcherInterface $dispatcher = null,
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

        $address = (string) $value;
        $result = $this->guards
            ->create($constraint->blockOn, $constraint->reviewOn)
            ->check($address);

        // Hand every decision to whoever is listening (default: the deny-log
        // listener). Listeners filter what they care about; the bundle never
        // hard-wires what happens to a decision beyond the violation below.
        $this->dispatcher?->dispatch(new GuardDecisionEvent($result, $address, $this->domainOf($address)));

        if ($result->isDenied()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reasons }}', implode(', ', $result->reasons))
                ->setCode(VerifiedEmail::DENIED_ERROR)
                ->addViolation();
        }
    }

    /**
     * Domain part for the event: lowercased, null when there is no @ (syntax
     * failure). Not personal data, unlike the local part.
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
