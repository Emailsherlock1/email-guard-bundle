<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\Validator;

use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;
use Emailsherlock\EmailGuardBundle\Tests\Support\GuardFactoryBuilder;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmailValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class VerifiedEmailValidatorTest extends ConstraintValidatorTestCase
{
    /** @var list<GuardDecisionEvent> */
    private array $events = [];

    protected function createValidator(): VerifiedEmailValidator
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GuardDecisionEvent::class, function (GuardDecisionEvent $e): void {
            $this->events[] = $e;
        });

        return new VerifiedEmailValidator(GuardFactoryBuilder::build(), $dispatcher);
    }

    public function testDispatchesAnEventForEveryDecision(): void
    {
        $this->validator->validate('jane@acme-corp.com', new VerifiedEmail());      // allow
        $this->validator->validate('x@mailinator.com', new VerifiedEmail());        // deny

        self::assertCount(2, $this->events);
        self::assertSame('allow', $this->events[0]->result->action->value);
        self::assertSame('deny', $this->events[1]->result->action->value);
    }

    public function testEventCarriesResultRawInputAndExtractedDomain(): void
    {
        $this->validator->validate('deleted+user274@deleted.invalid', new VerifiedEmail());

        self::assertCount(1, $this->events);
        $event = $this->events[0];
        self::assertSame('invalid', $event->result->verdict->value);
        self::assertContains('reserved_tld', $event->result->reasons);
        // input carries the raw address (PII, the listener's responsibility);
        // domain is the extracted, non-PII part.
        self::assertSame('deleted+user274@deleted.invalid', $event->input);
        self::assertSame('deleted.invalid', $event->domain);
    }

    public function testEventDomainIsNullOnSyntaxFailureWithoutAt(): void
    {
        $this->validator->validate('not-an-address', new VerifiedEmail());

        self::assertNull($this->events[0]->domain);
        self::assertSame('not-an-address', $this->events[0]->input);
    }

    public function testNullIsSkipped(): void
    {
        $this->validator->validate(null, new VerifiedEmail());
        $this->assertNoViolation();
        self::assertSame([], $this->events);
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
