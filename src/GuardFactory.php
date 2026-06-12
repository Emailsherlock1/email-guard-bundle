<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle;

use Emailsherlock\EmailGuard\EmailGuard;
use Emailsherlock\EmailGuard\Http\TransportInterface;
use Emailsherlock\EmailGuard\Local\DisposableSnapshot;

/**
 * Builds EmailGuard instances from the bundle configuration. The default
 * instance is shared; per-constraint block_on/review_on overrides get their
 * own instance because the core guard is immutable by design.
 */
final class GuardFactory
{
    private ?EmailGuard $default = null;

    /**
     * @param array<string, mixed> $config bundle config, key-compatible with
     *   the core library (api_key, block_on, review_on, fail_open,
     *   timeout_ms, base_url)
     */
    public function __construct(
        private readonly array $config,
        private readonly ?TransportInterface $transport = null,
        private readonly ?DisposableSnapshot $snapshot = null,
    ) {
    }

    /**
     * @param list<string>|null $blockOn null keeps the configured default
     * @param list<string>|null $reviewOn null keeps the configured default
     */
    public function create(?array $blockOn = null, ?array $reviewOn = null): EmailGuard
    {
        if ($blockOn === null && $reviewOn === null) {
            return $this->default ??= $this->build($this->config);
        }

        $config = $this->config;
        if ($blockOn !== null) {
            $config['block_on'] = $blockOn;
        }
        if ($reviewOn !== null) {
            $config['review_on'] = $reviewOn;
        }

        return $this->build($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function build(array $config): EmailGuard
    {
        return new EmailGuard($config, $this->transport, $this->snapshot);
    }
}
