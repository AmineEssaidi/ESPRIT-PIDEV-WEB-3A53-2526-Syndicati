<?php

namespace App\Form\Residence;

use App\Entity\Residence\Residence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use App\Form\DataTransformer\BlocsToArrayTransformer;

class ResidenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomR', TextType::class, [
                'label' => 'Residence Name',
                'attr' => ['placeholder' => 'Enter residence name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a residence title.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'The title must be at least {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9\s]+$/',
                        'message' => 'The title cannot contain special characters.',
                    ]),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Address',
                'attr' => ['placeholder' => 'Enter full address'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a geographic location.']),
                    new Length([
                        'min' => 10,
                        'minMessage' => 'The location must be at least {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9\s,]+$/',
                        'message' => 'The location cannot contain special characters (except commas).',
                    ]),
                ],
            ])
            ->add('nAppartements', ChoiceType::class, [
                'label' => 'Apartments Per Floor',
                'choices' => array_combine(range(1, 10), range(1, 10)),
                'attr' => ['class' => 'evenement-edit-input']
            ])
            ->add('nEtages', ChoiceType::class, [
                'label' => 'Number of Floors',
                'choices' => array_combine(range(0, 5), range(0, 5)),
                'attr' => ['class' => 'evenement-edit-input']
            ])
            ->add('nBlocs', ChoiceType::class, [
                'label' => 'Blocs (Select Multiple)',
                'choices' => array_combine(['A', 'B', 'C', 'D', 'E'], ['A', 'B', 'C', 'D', 'E']),
                'multiple' => true,
                'expanded' => true,  // Use checkboxes for better UX
                'attr' => ['class' => 'blocs-checkbox-group']
            ])
            ->add('imageR', FileType::class, [
                'label' => 'Residence Image',
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
        ;

        // Use CallbackTransformer to handle Array <-> String conversion
        $builder->get('nBlocs')->addModelTransformer(new \Symfony\Component\Form\CallbackTransformer(
            function ($blocsAsString) {
                // Transform the string to an array
                return $blocsAsString ? explode(',', $blocsAsString) : [];
            },
            function ($blocsAsArray) {
                // Transform the array back to a string
                return is_array($blocsAsArray) ? implode(',', $blocsAsArray) : '';
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Residence::class,
        ]);
    }
}
