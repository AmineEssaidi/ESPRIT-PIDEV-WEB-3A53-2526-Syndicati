<?php

namespace App\Form\Residence;

use App\Entity\Residence\Appartement;
use App\Entity\Residence\Maintenance;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('etat_app', ChoiceType::class, [
            'label'       => 'État général',
            'placeholder' => '-- Choisir Etat --',
            'required'    => false,
            'choices'     => [
                'Bon'     => 'bon',
                'Moyen'   => 'moyen',
                'Mauvais' => 'mauvais',
            ],
            'attr' => ['class' => 'form-select glass-input'],
        ])
        ->add('etat_plomberie', ChoiceType::class, [
            'label'       => 'État général',
            'placeholder' => '-- Choisir Etat --',
            'required'    => false,
            'choices'     => [
                'Bon'     => 'bon',
                'Moyen'   => 'moyen',
                'Mauvais' => 'mauvais',
            ],
            'attr' => ['class' => 'form-select glass-input'],
        ])
        ->add('etat_electricite', ChoiceType::class, [
            'label'       => 'État général',
            'placeholder' => '-- Choisir Etat --',
            'required'    => false,
            'choices'     => [
                'Bon'     => 'bon',
                'Moyen'   => 'moyen',
                'Mauvais' => 'mauvais',
            ],
            'attr' => ['class' => 'form-select glass-input'],
        ])
        ->add('etat_chauffage', ChoiceType::class, [
            'label'       => 'État général',
            'placeholder' => '-- Choisir Etat --',
            'required'    => false,
            'choices'     => [
                'Bon'     => 'bon',
                'Moyen'   => 'moyen',
                'Mauvais' => 'mauvais',
            ],
            'attr' => ['class' => 'form-select glass-input'],
        ])
            ->add('date_derniere_maintenance', DateTimeType::class, [
                'label' => 'Last Maintenance Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description_maint', TextareaType::class)
            ->add('appartement', EntityType::class, [
                'class' => Appartement::class,
                'choice_label' => 'id_app',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maintenance::class,
        ]);
    }
}
