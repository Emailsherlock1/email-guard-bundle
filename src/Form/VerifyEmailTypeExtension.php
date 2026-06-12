<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Form;

use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds a 'verify_email' option to every form field:
 *
 *     $builder->add('email', EmailType::class, ['verify_email' => true]);
 *
 * When true, the field gets a VerifiedEmail constraint with the bundle
 * defaults. Pass an explicit VerifiedEmail in 'constraints' instead when a
 * field needs its own block_on policy; the option then adds nothing on top.
 */
final class VerifyEmailTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('verify_email', false);
        $resolver->setAllowedTypes('verify_email', 'bool');

        $resolver->addNormalizer('constraints', static function (Options $options, mixed $constraints): array {
            $constraints = \is_array($constraints) ? $constraints : [$constraints];
            if (!$options['verify_email']) {
                return $constraints;
            }
            foreach ($constraints as $existing) {
                if ($existing instanceof VerifiedEmail) {
                    return $constraints;
                }
            }
            $constraints[] = new VerifiedEmail();

            return $constraints;
        });
    }
}
