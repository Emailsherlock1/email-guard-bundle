<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\EventListener;

use Emailsherlock\EmailGuard\Action;
use Emailsherlock\EmailGuard\GuardReporter;
use Emailsherlock\EmailGuard\Http\TransportInterface;
use Emailsherlock\EmailGuard\Http\TransportResponse;
use Emailsherlock\EmailGuard\Result;
use Emailsherlock\EmailGuard\Verdict;
use Emailsherlock\EmailGuardBundle\EventListener\GuardTelemetryFlushListener;
use PHPUnit\Framework\TestCase;

/**
 * GuardReporter is final (cannot be doubled), so this drives a real reporter
 * with a counting transport stub: after the listener fires, the queued event
 * must have been posted exactly once.
 */
final class GuardTelemetryFlushListenerTest extends TestCase
{
    public function testInvokeFlushesQueuedEvents(): void
    {
        $transport = new class implements TransportInterface {
            public int $calls = 0;

            public function post(string $url, array $headers, string $body, int $timeoutMs): TransportResponse
            {
                $this->calls++;

                return new TransportResponse(202, '{"accepted":1,"rejected":0}');
            }
        };

        $reporter = new GuardReporter(apiKey: 'k', transport: $transport);
        $reporter->record(new Result(Verdict::Disposable, Action::Deny, ['disposable_provider'], false, false), 'mailinator.com');

        (new GuardTelemetryFlushListener($reporter))();

        self::assertSame(1, $transport->calls);
        self::assertSame([], $reporter->pending());
    }
}
