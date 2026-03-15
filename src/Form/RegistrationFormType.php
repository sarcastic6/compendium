<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\StrongPassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'auth.register.name',
                'constraints' => [
                    new NotBlank(message: 'auth.name.not_blank'),
                    new Length(max: 255, maxMessage: 'auth.name.too_long'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'auth.register.email',
                'constraints' => [
                    new NotBlank(message: 'auth.email.not_blank'),
                    new Email(message: 'auth.email.invalid'),
                ],
            ])
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
        ]);
    }
}
