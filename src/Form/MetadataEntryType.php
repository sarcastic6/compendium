<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MetadataType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Sub-form for a single metadata entry (type + name).
 * Used inside the CollectionType on WorkFormType.
 */
class MetadataEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metadataType', EntityType::class, [
                'class' => MetadataType::class,
                'choice_label' => 'name',
                'label' => false,
                'placeholder' => 'work.field.metadata',
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'work.field.metadata'],
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
