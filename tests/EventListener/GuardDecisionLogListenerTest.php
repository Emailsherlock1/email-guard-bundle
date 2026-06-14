<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\EventListener;

use Emailsherlock\EmailGuard\Action;
use Emailsherlock\EmailGuard\Result;
use Emailsherlock\EmailGuard\Verdict;
use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;
use Emailsherlock\EmailGuardBundle\EventListener\GuardDecisionLogListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * The default deny-log listener (formerly inline in the validator, EM-1013):
 * logs denies as email_guard.denied, domain only, never the address.
 */
final class GuardDecisionLogListenerTest extends TestCase
{
    /** @var list<array{message: string|\Stringable, context: array<mixed>}> */
    private array $logs = [];

    public function testLogsDeniedDecisionWithDomainOnlyNeverTheAddress(): void
    {
        $event = new GuardDecisionEvent(
            new Result(Verdict::Invalid, Action::Deny, ['reserved_tld'], false, false),
            'deleted+user274@deleted.invalid',
            'deleted.invalid',
        );

        ($this->listener())($event);

        self::assertCount(1, $this->logs);
        self::assertSame('email_guard.denied', (string) $this->logs[0]['message']);
        $ctx = $this->logs[0]['context'];
        self::assertSame('deleted.invalid', $ctx['domain']);
        self::assertSame('invalid', $ctx['verdict']);
        self::assertContains('reserved_tld', $ctx['reasons']);
        self::assertSame('local', $ctx['source']);
        self::assertStringNotContainsString('deleted+user274', json_encode($this->logs));
        self::assertStringNotContainsString('@', json_encode($ctx));
    }

    public function testRemoteSourceWhenApiWasCalled(): void
    {
        $event = new GuardDecisionEvent(
            new Result(Verdict::Invalid, Action::Deny, ['mailbox_not_found'], false, true),
            'ghost@real-corp.com',
            'real-corp.com',
        );

        ($this->listener())($event);

        self::assertSame('remote', $this->logs[0]['context']['source']);
    }

    public function testDoesNotLogAllowedDecision(): void
    {
        $event = new GuardDecisionEvent(
            new Result(Verdict::Valid, Action::Allow, [], false, false),
            'jane@acme-corp.com',
            'acme-corp.com',
        );

        ($this->listener())($event);

        self::assertSame([], $this->logs);
    }

    public function testNoLoggerIsHarmless(): void
    {
        $event = new GuardDecisionEvent(
            new Result(Verdict::Disposable, Action::Deny, ['disposable_provider'], false, false),
            'x@mailinator.com',
            'mailinator.com',
        );

        // No logger wired: must not throw.
        (new GuardDecisionLogListener(null))($event);
        $this->expectNotToPerformAssertions();
    }

    private function listener(): GuardDecisionLogListener
    {
        $logger = new class($this->logs) extends AbstractLogger {
            /** @param list<array<string, mixed>> $sink */
            public function __construct(private array &$sink)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->sink[] = ['message' => $message, 'context' => $context];
            }
        };

        return new GuardDecisionLogListener($logger);
    }
}
