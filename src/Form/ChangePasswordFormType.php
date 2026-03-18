<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\StrongPassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['require_current_password']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'profile.field.current_password',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'auth.password.not_blank'),
                ],
            ]);
        }

        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'auth.register.password'],
                'second_options' => ['label' => 'auth.register.password_confirm'],
                'invalid_message' => 'auth.password.mismatch',
                'constraints' => [
                    new NotBlank(message: 'auth.password.not_blank'),
                    new StrongPassword(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
            'require_current_password' => true,
        ]);

        $resolver->setAllowedTypes('require_current_password', 'bool');
    }
}
