<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\Validator;

use Emailsherlock\EmailGuardBundle\Tests\Support\GuardFactoryBuilder;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmailValidator;
use Psr\Log\AbstractLogger;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class VerifiedEmailValidatorTest extends ConstraintValidatorTestCase
{
    /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
    private array $logs = [];

    protected function createValidator(): VerifiedEmailValidator
    {
        $logger = new class($this->logs) extends AbstractLogger {
            /** @param list<array<string, mixed>> $sink */
            public function __construct(private array &$sink)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->sink[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };

        return new VerifiedEmailValidator(GuardFactoryBuilder::build(), $logger);
    }

    public function testDeniedDecisionIsLoggedWithDomainOnlyNeverTheAddress(): void
    {
        $this->validator->validate('deleted+user274@deleted.invalid', new VerifiedEmail());

        self::assertCount(1, $this->logs);
        self::assertSame('email_guard.denied', (string) $this->logs[0]['message']);
        $ctx = $this->logs[0]['context'];
        self::assertSame('deleted.invalid', $ctx['domain']);
        self::assertSame('invalid', $ctx['verdict']);
        self::assertContains('reserved_tld', $ctx['reasons']);
        self::assertSame('local', $ctx['source']);
        // The local part / full address must never appear in the log.
        self::assertStringNotContainsString('deleted+user274', json_encode($this->logs));
        self::assertStringNotContainsString('@', json_encode($ctx));
    }

    public function testAllowedDecisionIsNotLogged(): void
    {
        $this->validator->validate('jane@acme-corp.com', new VerifiedEmail());

        self::assertSame([], $this->logs);
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
