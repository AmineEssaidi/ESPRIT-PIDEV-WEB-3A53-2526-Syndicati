<?php

namespace App\Form\Syndicat;

use App\Entity\Syndicat\Reponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titrereponse', TextType::class, [
                'label' => 'Titre de la réponse',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('messagereponse', TextareaType::class, [
                'label' => 'Message de réponse',
                'attr' => ['class' => 'form-control', 'rows' => 5]
            ])
            ->add('imagereponse', FileType::class, [
                'label' => 'Image (Optional)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reponse::class,
        ]);
    }
}
