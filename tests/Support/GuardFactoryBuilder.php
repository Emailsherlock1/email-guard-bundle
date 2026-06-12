<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\Support;

use Emailsherlock\EmailGuard\Local\DisposableSnapshot;
use Emailsherlock\EmailGuardBundle\GuardFactory;

/**
 * Builds a GuardFactory wired like the bundle defaults but with the tiny
 * test snapshot, no API key, and therefore zero network access.
 */
final class GuardFactoryBuilder
{
    /** @param array<string, mixed> $overrides */
    public static function build(array $overrides = []): GuardFactory
    {
        $config = array_merge([
            'api_key' => null,
            'block_on' => ['invalid', 'disposable'],
            'review_on' => [],
            'fail_open' => true,
            'timeout_ms' => 800,
            'base_url' => 'https://api.emailsherlock.com',
        ], $overrides);

        return new GuardFactory(
            $config,
            null,
            new DisposableSnapshot(__DIR__ . '/../fixtures/disposable-snapshot.json'),
        );
    }
}
