<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle;

use Emailsherlock\EmailGuard\EmailGuard;
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

        $services->set('email_guard.factory', GuardFactory::class)
            ->args([$config]);
        $services->alias(GuardFactory::class, 'email_guard.factory');

        $services->set('email_guard.guard', EmailGuard::class)
            ->factory([service('email_guard.factory'), 'create']);
        $services->alias(EmailGuard::class, 'email_guard.guard');

        $services->set('email_guard.validator.verified_email', VerifiedEmailValidator::class)
            ->args([service('email_guard.factory')])
            ->tag('validator.constraint_validator');

        if (class_exists(FormType::class)) {
            $services->set('email_guard.form.type_extension', VerifyEmailTypeExtension::class)
                ->tag('form.type_extension');
        }
    }
}
