<?php

namespace App\Form;

use App\Entity\Candidature;
use App\Entity\Recruitment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CandidatureType extends AbstractType
{
    public function __construct(private AuthorizationCheckerInterface $authChecker) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recruitment', EntityType::class, [
                'class' => Recruitment::class,
                'choice_label' => 'title',
                'label' => 'Recrutement'
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Lettre de motivation / Message'
            ])
        ;

        // If user is a responsable or admin, they can edit the status
        if ($this->authChecker->isGranted('ROLE_RESPONSABLE')) {
            $builder->add('status', ChoiceType::class, [
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptée' => 'Acceptée',
                    'Refusée' => 'Refusée',
                ],
                'label' => 'Statut de la candidature'
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
        ]);
    }
}
