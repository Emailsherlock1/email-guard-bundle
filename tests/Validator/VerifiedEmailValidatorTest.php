<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\Validator;

use Emailsherlock\EmailGuardBundle\Tests\Support\GuardFactoryBuilder;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmailValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class VerifiedEmailValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): VerifiedEmailValidator
    {
        return new VerifiedEmailValidator(GuardFactoryBuilder::build());
    }

    public function testNullIsSkipped(): void
    {
        $this->validator->validate(null, new VerifiedEmail());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsSkipped(): void
    {
        $this->validator->validate('', new VerifiedEmail());
        $this->assertNoViolation();
    }

    public function testCleanAddressPassesWithoutApiKey(): void
    {
        $this->validator->validate('jane@acme-corp.com', new VerifiedEmail());
        $this->assertNoViolation();
    }

    public function testReservedTldIsDenied(): void
    {
        $constraint = new VerifiedEmail();
        $this->validator->validate('deleted+user274@deleted.invalid', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ reasons }}', 'reserved_tld')
            ->setCode(VerifiedEmail::DENIED_ERROR)
            ->assertRaised();
    }

    public function testBadSyntaxIsDenied(): void
    {
        $constraint = new VerifiedEmail();
        $this->validator->validate('not-an-address', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ reasons }}', 'bad_syntax')
            ->setCode(VerifiedEmail::DENIED_ERROR)
            ->assertRaised();
    }

    public function testDisposableIsDenied(): void
    {
        $constraint = new VerifiedEmail();
        $this->validator->validate('x@mailinator.com', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ reasons }}', 'disposable_provider')
            ->setCode(VerifiedEmail::DENIED_ERROR)
            ->assertRaised();
    }

    public function testPerConstraintBlockOnOverride(): void
    {
        $this->validator->validate('x@mailinator.com', new VerifiedEmail(blockOn: ['invalid']));
        $this->assertNoViolation();
    }

    public function testCustomMessage(): void
    {
        $constraint = new VerifiedEmail(message: 'Use a permanent address.');
        $this->validator->validate('x@yopmail.com', $constraint);

        $this->buildViolation('Use a permanent address.')
            ->setParameter('{{ reasons }}', 'disposable_provider')
            ->setCode(VerifiedEmail::DENIED_ERROR)
            ->assertRaised();
    }
}
