<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'autocomplete' => 'username',
                    'placeholder' => 'Choose a username',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a username'),
                    new Length(
                        min: 3,
                        minMessage: 'Username must be at least {{ limit }} characters',
                        max: 180,
                    ),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'your@email.com'],
            ])
            ->add('role', ChoiceType::class, [
                'label'    => 'I am a',
                'mapped'   => false,
                'expanded' => true,   // renders as radio buttons
                'multiple' => false,
                'choices'  => [
                    '🎓  Student (Étudiant)'       => User::ROLE_ETUDIANT,
                    '👔  Responsable'              => User::ROLE_RESPONSABLE,
                    '👑  Club President (Président)' => User::ROLE_PRESIDENT,
                ],
                'constraints' => [
                    new NotBlank(message: 'Please select a role'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr'   => [
                    'autocomplete' => 'new-password',
                    'placeholder'  => 'At least 6 characters',
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(
                        min: 6,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                        max: 4096,
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped'      => false,
                'constraints' => [
                    new IsTrue(message: 'You should agree to our terms.'),
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
