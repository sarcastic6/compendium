<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MetadataType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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
                // Hidden visually — the section JS sets this value when the user clicks
                // "Add" in a specific metadata type section. Authors are excluded because
                // they have their own dedicated section above.
                // row_attr hides the entire row wrapper (including its mb-3 margin) so it
                // doesn't push the name input down inside the flex collection item.
                'row_attr' => ['style' => 'display:none'],
                'attr' => ['data-metadata-type-select' => ''],
                'query_builder' => static function (EntityRepository $er): QueryBuilder {
                    return $er->createQueryBuilder('mt')
                        ->where('mt.name != :author')
                        ->setParameter('author', 'Author')
                        ->orderBy('mt.name', 'ASC');
                },
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'work.field.metadata_name_placeholder'],
                'constraints' => [new NotBlank()],
            ])
            ->add('link', HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
