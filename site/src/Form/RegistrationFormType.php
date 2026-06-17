<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'input',
                    'autocomplete' => 'email',
                    'placeholder' => 'toi@exemple.fr',
                ],
                'label_attr' => ['class' => 'field-label'],
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Nom affiché',
                'required' => false,
                'attr' => [
                    'class' => 'input',
                    'autocomplete' => 'nickname',
                    'placeholder' => 'Optionnel',
                ],
                'label_attr' => ['class' => 'field-label'],
            ])
            ->add('plainPassword', PasswordType::class, [
                // Le mot de passe en clair n'est jamais persisté : il est hashé dans le contrôleur.
                'mapped' => false,
                'label' => 'Mot de passe',
                'attr' => [
                    'class' => 'input',
                    'autocomplete' => 'new-password',
                ],
                'label_attr' => ['class' => 'field-label'],
                'constraints' => [
                    new NotBlank(message: 'Choisis un mot de passe.'),
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
