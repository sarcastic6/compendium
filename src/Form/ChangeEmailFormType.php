<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangeEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label'       => 'profile.field.current_password',
                'mapped'      => false,
                'constraints' => [
                    new NotBlank(message: 'auth.password.not_blank'),
                ],
            ])
            ->add('newEmail', EmailType::class, [
                'label'       => 'profile.field.new_email',
                'mapped'      => false,
                'constraints' => [
                    new NotBlank(message: 'auth.email.not_blank'),
                    new Email(message: 'auth.email.invalid'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
