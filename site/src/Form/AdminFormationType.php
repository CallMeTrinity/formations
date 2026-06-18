<?php

namespace App\Form;

use App\Entity\Formation;
use App\Entity\Tag;
use App\Enum\Difficulty;
use App\Enum\Status;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Édition des métadonnées admin d'une formation (champs préservés par la sync).
 * La visibilité se règle à part, en accès rapide depuis la liste.
 */
class AdminFormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class' => Status::class,
                'choice_label' => fn (Status $status) => $status->label(),
                'label' => 'Statut éditorial',
                'attr' => ['class' => 'select focus-ring'],
                'label_attr' => ['class' => 'field-label'],
            ])
            ->add('difficulty', EnumType::class, [
                'class' => Difficulty::class,
                'choice_label' => fn (Difficulty $difficulty) => $difficulty->label(),
                'required' => false,
                'placeholder' => 'Non définie',
                'label' => 'Difficulté',
                'attr' => ['class' => 'select focus-ring'],
                'label_attr' => ['class' => 'field-label'],
            ])
            ->add('estimatedMinutes', IntegerType::class, [
                'required' => false,
                'label' => 'Durée estimée (minutes)',
                'attr' => [
                    'class' => 'input focus-ring',
                    'min' => 0,
                    'placeholder' => 'Ex. 120',
                ],
                'label_attr' => ['class' => 'field-label'],
                'constraints' => [
                    new Range(
                        min: 0,
                        max: 100000,
                        notInRangeMessage: 'Indique une valeur entre {{ min }} et {{ max }} minutes.',
                    ),
                ],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => true, // rendu en cases à cocher
                // Indispensable avec multiple : force l'appel de add/removeTag()
                // sur l'entité plutôt qu'un remplacement direct de la collection.
                'by_reference' => false,
                'required' => false,
                'label' => 'Tags',
                'query_builder' => fn (EntityRepository $repo) => $repo
                    ->createQueryBuilder('t')
                    ->orderBy('t.label', 'ASC'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}
