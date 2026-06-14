<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\EventListener;

use Emailsherlock\EmailGuard\GuardDecisionSource;
use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;
use Psr\Log\LoggerInterface;

/**
 * Default listener: logs every blocked decision as `email_guard.denied`
 * (domain, verdict, reasons, source) via PSR-3. Domain only, never the
 * address. Key-independent, so blocks are visible even without telemetry
 * configured.
 *
 * This is just one subscriber on GuardDecisionEvent. To route blocks
 * elsewhere (own channel, own store, metrics, webhook), register your own
 * listener; to silence this one, override the
 * `email_guard.event.decision_log_listener` service or drop its tag.
 */
final class GuardDecisionLogListener
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(GuardDecisionEvent $event): void
    {
        if ($this->logger === null || !$event->result->isDenied()) {
            return;
        }

        $this->logger->info('email_guard.denied', [
            'domain' => $event->domain,
            'verdict' => $event->result->verdict->value,
            'reasons' => $event->result->reasons,
            'source' => $event->result->apiCalled ? GuardDecisionSource::REMOTE : GuardDecisionSource::LOCAL,
        ]);
    }
}
