<?php

namespace App\Form\Syndicat;

use App\Entity\Syndicat\Reclamation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titrereclamations', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'syndicat-form-input', 'placeholder' => 'What is this regarding?']
            ])
            ->add('descreclamation', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'syndicat-form-input', 'rows' => 4, 'placeholder' => 'Please provide detailed information...']
            ])
            ->add('datereclamation', DateTimeType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'syndicat-form-input flatpickr-input']
            ])
            ->add('statutreclamation', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'active' => 'active',
                    'en_attente' => 'en_attente',
                    'refuse' => 'refuse',
                    'termine' => 'termine'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('imagereclamation', FileType::class, [
                'label' => 'Attachments',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => ['class' => 'syndicat-form-input', 'accept' => 'image/*', 'multiple' => 'multiple'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}
