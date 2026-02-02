<?php

namespace App\Form\Backend;

use App\Entity\Reclamations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class ReclamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titrereclamations', null, [
                'label' => 'Titre de la réclamation',
            ])

            ->add('descreclamation', TextareaType::class, [
                'label' => 'Description',
            ])

            ->add('datereclamation', DateTimeType::class, [
                'label' => 'Date de réclamation',
                'widget' => 'single_text',
            ])

            ->add('statutreclamation', null, [
                'label' => 'Statut',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamations::class,
        ]);
    }
}
