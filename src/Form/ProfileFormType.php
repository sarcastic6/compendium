<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'profile.field.name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'profile.field.email',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                // Not required — only validated by the controller when the email address changes.
                'label' => 'profile.field.current_password',
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
