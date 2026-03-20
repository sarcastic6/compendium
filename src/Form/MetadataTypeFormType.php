<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MetadataType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MetadataTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin.metadata_types.field.name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('multipleAllowed', CheckboxType::class, [
                'label' => 'admin.metadata_types.field.multiple_allowed',
                'required' => false,
            ])
            ->add('showAsDropdown', CheckboxType::class, [
                'label' => 'admin.metadata_types.field.show_as_dropdown',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MetadataType::class,
            'translation_domain' => 'messages',
        ]);
    }
}
