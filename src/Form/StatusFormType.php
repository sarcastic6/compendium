<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Status;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class StatusFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin.statuses.field.name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('hasBeenStarted', CheckboxType::class, [
                'label' => 'admin.statuses.field.has_been_started',
                'required' => false,
            ])
            ->add('countsAsRead', CheckboxType::class, [
                'label' => 'admin.statuses.field.counts_as_read',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'admin.statuses.field.is_active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Status::class,
            'translation_domain' => 'messages',
        ]);
    }
}
