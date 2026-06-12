<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Validator;

use Emailsherlock\EmailGuardBundle\GuardFactory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class VerifiedEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly GuardFactory $guards,
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
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ reasons }}', implode(', ', $result->reasons))
                ->setCode(VerifiedEmail::DENIED_ERROR)
                ->addViolation();
        }
    }
}
