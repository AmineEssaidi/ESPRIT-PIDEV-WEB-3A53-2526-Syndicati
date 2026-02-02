<?php

namespace App\Form\Backend;

use App\Entity\Reponses;
use App\Entity\Reclamations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReponsesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('messagereponse', TextareaType::class, [
                'label' => 'Réponse',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Écrivez votre réponse ici...',
                ],
            ])

            ->add('datereponse', DateTimeType::class, [
                'label' => 'Date de réponse',
                'widget' => 'single_text',
            ])


            
            ;}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reponses::class,
        ]);
    }
}
