<?php

namespace App\Form;

use App\Entity\Club;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mode = $options['role_mode'];

        if ($mode === 'all' || $mode === 'president') {
            $builder
                ->add('nom', TextType::class, [
                    'label' => 'Nom du Club',
                    'attr' => ['class' => 'form-control']
                ])
                ->add('logoFile', FileType::class, [
                    'label' => 'Logo (fichier PNG, JPG)',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control']
                ]);
        }

        if ($mode === 'all' || $mode === 'responsable') {
            $builder
                ->add('description', TextareaType::class, [
                    'label' => 'Description',
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'rows' => 5]
                ])
                ->add('domaine', TextType::class, [
                    'label' => 'Domaine',
                    'attr' => ['class' => 'form-control']
                ]);
            // Note: 'status' est géré par le controller (défaut 'En attente'), pas par le formulaire
        }

        if ($mode === 'all') {
            // Seul l'admin peut modifier le statut via le formulaire d'édition
            $builder->add('status', TextType::class, [
                'label' => 'Statut',
                'attr' => ['class' => 'form-control']
            ]);
        }

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
            'role_mode' => 'all',
        ]);
    }
}