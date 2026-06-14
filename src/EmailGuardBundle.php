<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle;

use Emailsherlock\EmailGuard\EmailGuard;
use Emailsherlock\EmailGuard\GuardReporter;
use Emailsherlock\EmailGuardBundle\Event\GuardDecisionEvent;
use Emailsherlock\EmailGuardBundle\EventListener\GuardDecisionLogListener;
use Emailsherlock\EmailGuardBundle\EventListener\GuardTelemetryFlushListener;
use Emailsherlock\EmailGuardBundle\Form\VerifyEmailTypeExtension;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmailValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Wires email-guard-core into Symfony. Configuration mirrors the spec's
 * config keys (email-guard-spec, section 6.3):
 *
 *     email_guard:
 *         api_key: '%env(default::EMAILGUARD_API_KEY)%'   # optional
 *         fail_open: true
 *         timeout_ms: 800
 *         block_on: ['invalid', 'disposable']
 *         review_on: []
 */
final class EmailGuardBundle extends AbstractBundle
{
    protected string $extensionAlias = 'email_guard';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('api_key')
                    ->defaultNull()
                    ->info('EmailSherlock API key. Without it the guard runs local checks only.')
                ->end()
                ->booleanNode('fail_open')
                    ->defaultTrue()
                    ->info('What a failed API call does: true lets the address through, false denies it.')
                ->end()
                ->integerNode('timeout_ms')
                    ->defaultValue(800)
                    ->min(1)
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://api.emailsherlock.com')
                ->end()
                ->arrayNode('block_on')
                    ->scalarPrototype()->end()
                    ->defaultValue(['invalid', 'disposable'])
                ->end()
                ->arrayNode('review_on')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // Decision-telemetry emitter (spec section 11). Key-gated and
        // fail-silent inside the reporter, so it is harmless without a key.
        $services->set('email_guard.reporter', GuardReporter::class)
            ->args([
                $config['api_key'],
                $config['base_url'],
                $config['timeout_ms'],
                'symfony-bundle',
            ]);
        $services->alias(GuardReporter::class, 'email_guard.reporter');

        $services->set('email_guard.factory', GuardFactory::class)
            ->args([$config, null, null, service('email_guard.reporter')]);
        $services->alias(GuardFactory::class, 'email_guard.factory');

        // Flush the batched events after the response (kernel.terminate), so a
        // form submit never waits on the report.
        $services->set('email_guard.telemetry_flush_listener', GuardTelemetryFlushListener::class)
            ->args([service('email_guard.reporter')])
            ->tag('kernel.event_listener', ['event' => 'kernel.terminate']);

        $services->set('email_guard.guard', EmailGuard::class)
            ->factory([service('email_guard.factory'), 'create']);
        $services->alias(EmailGuard::class, 'email_guard.guard');

        $services->set('email_guard.validator.verified_email', VerifiedEmailValidator::class)
            ->args([service('email_guard.factory'), service('event_dispatcher')->nullOnInvalid()])
            ->tag('validator.constraint_validator');

        // Default listener: logs denies (domain only, key-independent). Just
        // one subscriber on the event; integrators add their own or drop this.
        $services->set('email_guard.event.decision_log_listener', GuardDecisionLogListener::class)
            ->args([service('logger')->nullOnInvalid()])
            ->tag('kernel.event_listener', ['event' => GuardDecisionEvent::class, 'method' => '__invoke']);

        if (class_exists(FormType::class)) {
            $services->set('email_guard.form.type_extension', VerifyEmailTypeExtension::class)
                ->tag('form.type_extension');
        }
    }
}
