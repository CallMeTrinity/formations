<?php

namespace App\Form;

use App\Entity\Tag;
use App\Entity\UserPreferences;
use App\Enum\Difficulty;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class UserPreferencesFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('preferredTags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => true, // rendu en cases à cocher
                // Indispensable avec multiple : force l'appel de add/removePreferredTag()
                // sur l'entité plutôt qu'un remplacement direct de la collection.
                'by_reference' => false,
                'required' => false,
                'label' => 'Thèmes qui t\'intéressent',
                'query_builder' => fn (EntityRepository $repo) => $repo
                    ->createQueryBuilder('t')
                    ->orderBy('t.label', 'ASC'),
            ])
            ->add('preferredDifficulty', EnumType::class, [
                'class' => Difficulty::class,
                'choice_label' => fn (Difficulty $difficulty) => $difficulty->label(),
                'required' => false,
                'placeholder' => 'Peu importe',
                'label' => 'Niveau préféré',
                'attr' => ['class' => 'select focus-ring'],
                'label_attr' => ['class' => 'field-label'],
            ])
            ->add('weeklyGoalMinutes', IntegerType::class, [
                'required' => false,
                'label' => 'Objectif hebdomadaire (minutes)',
                'attr' => [
                    'class' => 'input focus-ring',
                    'min' => 0,
                    'placeholder' => 'Ex. 120',
                ],
                'label_attr' => ['class' => 'field-label'],
                'constraints' => [
                    new Range(
                        min: 0,
                        max: 10080, // nombre de minutes dans une semaine
                        notInRangeMessage: 'Indique une valeur entre {{ min }} et {{ max }} minutes.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserPreferences::class,
        ]);
    }
}
