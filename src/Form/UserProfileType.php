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

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'autocomplete' => 'username',
                    'placeholder' => 'Votre nom',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(
                        min: 3,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caracteres.',
                        max: 180,
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'votre@email.com',
                ],
                'constraints' => [
                    new NotBlank(message: 'L email est obligatoire.'),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'placeholder' => 'Requis pour changer le mot de passe',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'mapped' => false,
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Laissez vide pour ne pas changer',
                ],
                'constraints' => [
                    new Length(
                        min: 6,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caracteres.',
                        max: 4096,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
