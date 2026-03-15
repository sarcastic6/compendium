<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\WorkFormDto;
use App\Entity\Language;
use App\Entity\Series;
use App\Enum\SourceType;
use App\Enum\WorkType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'work.field.type',
                'choices' => WorkType::cases(),
                'choice_label' => static fn (WorkType $type) => $type->value,
                'choice_value' => static fn (?WorkType $type) => $type?->value,
                'placeholder' => '',
            ])
            ->add('title', TextType::class, [
                'label' => 'work.field.title',
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'work.field.summary',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('series', EntityType::class, [
                'label' => 'work.field.series',
                'class' => Series::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '',
            ])
            ->add('placeInSeries', IntegerType::class, [
                'label' => 'work.field.place_in_series',
                'required' => false,
            ])
            ->add('language', EntityType::class, [
                'label' => 'work.field.language',
                'class' => Language::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '',
            ])
            ->add('publishedDate', DateType::class, [
                'label' => 'work.field.published_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('lastUpdatedDate', DateType::class, [
                'label' => 'work.field.last_updated_date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('words', IntegerType::class, [
                'label' => 'work.field.words',
                'required' => false,
            ])
            ->add('chapters', IntegerType::class, [
                'label' => 'work.field.chapters',
                'required' => false,
            ])
            ->add('link', UrlType::class, [
                'label' => 'work.field.link',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('sourceType', ChoiceType::class, [
                'label' => 'work.field.source_type',
                'choices' => SourceType::cases(),
                'choice_label' => static fn (SourceType $type) => $type->value,
                'choice_value' => static fn (?SourceType $type) => $type?->value,
            ])
            ->add('starred', CheckboxType::class, [
                'label' => 'work.field.starred',
                'required' => false,
            ])
            ->add('authors', CollectionType::class, [
                'label' => 'work.field.authors',
                'entry_type' => AuthorEntryType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('metadata', CollectionType::class, [
                'label' => 'work.field.metadata',
                'entry_type' => MetadataEntryType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkFormDto::class,
            'translation_domain' => 'messages',
        ]);
    }
}
