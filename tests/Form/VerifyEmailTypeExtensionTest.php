<?php

declare(strict_types=1);

namespace Emailsherlock\EmailGuardBundle\Tests\Form;

use Emailsherlock\EmailGuardBundle\Form\VerifyEmailTypeExtension;
use Emailsherlock\EmailGuardBundle\Tests\Support\GuardFactoryBuilder;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmail;
use Emailsherlock\EmailGuardBundle\Validator\VerifiedEmailValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;

final class VerifyEmailTypeExtensionTest extends TestCase
{
    public function testVerifyEmailOptionDeniesJunk(): void
    {
        $form = $this->formFactory()
            ->createBuilder(FormType::class)
            ->add('email', EmailType::class, ['verify_email' => true])
            ->getForm();

        $form->submit(['email' => 'deleted+user274@deleted.invalid']);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('email')->getErrors());
    }

    public function testVerifyEmailOptionLetsCleanAddressesThrough(): void
    {
        $form = $this->formFactory()
            ->createBuilder(FormType::class)
            ->add('email', EmailType::class, ['verify_email' => true])
            ->getForm();

        $form->submit(['email' => 'jane@acme-corp.com']);

        $this->assertTrue($form->isValid());
    }

    public function testOptionDefaultsToOff(): void
    {
        $form = $this->formFactory()
            ->createBuilder(FormType::class)
            ->add('email', EmailType::class)
            ->getForm();

        $form->submit(['email' => 'x@mailinator.com']);

        $this->assertTrue($form->isValid());
    }

    public function testExplicitConstraintIsNotDuplicated(): void
    {
        $form = $this->formFactory()
            ->createBuilder(FormType::class)
            ->add('email', EmailType::class, [
                'verify_email' => true,
                'constraints' => [new VerifiedEmail(blockOn: ['invalid'])],
            ])
            ->getForm();

        // The explicit constraint allows disposables; the option must not
        // stack a second, stricter default constraint on top.
        $form->submit(['email' => 'x@mailinator.com']);

        $this->assertTrue($form->isValid());
    }

    private function formFactory(): FormFactoryInterface
    {
        $guardValidator = new VerifiedEmailValidator(GuardFactoryBuilder::build());

        $constraintValidatorFactory = new class($guardValidator) implements ConstraintValidatorFactoryInterface {
            private readonly ConstraintValidatorFactory $fallback;

            public function __construct(private readonly VerifiedEmailValidator $guardValidator)
            {
                $this->fallback = new ConstraintValidatorFactory();
            }

            public function getInstance(\Symfony\Component\Validator\Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint->validatedBy() === VerifiedEmailValidator::class) {
                    return $this->guardValidator;
                }

                return $this->fallback->getInstance($constraint);
            }
        };

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($constraintValidatorFactory)
            ->getValidator();

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addTypeExtension(new VerifyEmailTypeExtension())
            ->getFormFactory();
    }
}
