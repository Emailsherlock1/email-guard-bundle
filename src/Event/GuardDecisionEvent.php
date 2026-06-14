<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Event;

use Emailsherlock\EmailGuard\Result;

/**
 * Dispatched once per guard decision (allow, deny, or review), so an
 * integrating app can react however it wants: log to its own channel, write
 * to its own store, push a metric, fire a webhook, alert Slack. The bundle
 * ships one default listener (GuardDecisionLogListener) that logs denies;
 * that listener is just one subscriber and can be replaced or removed.
 *
 * PII note: `input` is the raw address as submitted, so a listener CAN see
 * the local part. That is deliberate (a hook that hides which address was
 * blocked is half useless, and the integrator already has the address in
 * their own form), but it makes PII the listener author's responsibility.
 * `domain` is the extracted, lowercased domain part and is not personal data;
 * prefer it when you only need the domain. The bundle's own log listener
 * uses `domain` only.
 */
final class GuardDecisionEvent
{
    public function __construct(
        public readonly Result $result,
        public readonly string $input,
        public readonly ?string $domain,
    ) {
    }
}
