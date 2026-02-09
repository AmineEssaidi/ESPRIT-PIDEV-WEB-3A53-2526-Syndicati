<?php

namespace App\Form\Evenement;

use App\Entity\Evenement\Participation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nb_accompagnants', IntegerType::class, [
                'label' => 'Number of guests',
                'attr' => ['min' => 0]
            ])
            ->add('commentaire_participation', TextareaType::class, [
                'label' => 'Comment',
                'required' => false
            ]);

        if ($options['is_admin']) {
            $builder->add('statut_participation', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'Status',
                'choices' => array_combine(Participation::STATUTS, Participation::STATUTS),
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
            'is_admin' => false,
        ]);
    }
}
