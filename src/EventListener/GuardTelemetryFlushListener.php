<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\EventListener;

use Emailsherlock\EmailGuard\GuardReporter;

/**
 * Flushes the queued guard-decision telemetry after the response is sent.
 *
 * Wired to kernel.terminate (the Symfony-idiomatic "after response" hook, the
 * framework's stand-in for fastcgi_finish_request), so a form submit never
 * waits on the report. The reporter itself is key-gated and fail-silent, so
 * this is a no-op without an API key and can never throw into the kernel.
 */
final class GuardTelemetryFlushListener
{
    public function __construct(
        private readonly GuardReporter $reporter,
    ) {
    }

    public function __invoke(): void
    {
        $this->reporter->flush();
    }
}
