<?php

namespace App\Form\Residence;

use App\Entity\Residence\Appartement;
use App\Entity\Residence\Residence;
use App\Entity\User\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AppartementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('residence', EntityType::class, [
                'class' => \App\Entity\Residence\Residence::class,
                'choice_label' => 'nomR',
                'label' => 'Residence',
                'placeholder' => 'Choose a Residence',
                'attr' => ['class' => 'residence-select'], // Add class for JS selection
            ])
            ->add('bloc', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Building Block',
                'attr' => [
                    'class' => 'bloc-input glass-input',
                    'id' => 'app-bloc-field',
                    'placeholder' => 'Select Bloc'
                ],
            ])
            ->add('floor', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Floor Level',
                'attr' => [
                    'class' => 'floor-input glass-input',
                    'id' => 'app-floor-field',
                    'placeholder' => 'Select Floor'
                ],
            ])
            ->add('number', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Apartment Number',
                'attr' => [
                    'class' => 'number-input glass-input',
                    'id' => 'app-number-field',
                    'placeholder' => 'Select Apartment'
                ],
            ])
            ->add('typeA', ChoiceType::class, [
                'label' => 'Apartment Type',
                'choices' => [
                    'Studio' => 'STUDIO',
                    'S+1' => 'S+1',
                    'S+2' => 'S+2',
                    'S+3' => 'S+3',
                    'S+4' => 'S+4',
                    'S+5' => 'S+5',
                ],
            ])
            ->add('parking', CheckboxType::class, [
                'label' => 'Parking Available',
                'required' => false,
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Available',
                'required' => false,
            ])
            ->add('imageA', FileType::class, [
                'label' => 'Image (Optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP)',
                    ])
                ],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'emailUser', // Correct property path for getEmailUser()
                'label' => 'Owner / User',
                'placeholder' => 'Select Owner',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appartement::class,
        ]);
    }
}
