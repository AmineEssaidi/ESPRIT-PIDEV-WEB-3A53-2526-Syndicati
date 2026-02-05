<?php

namespace App\Form\Backend;

use App\Entity\Backend\Appartement;
use App\Entity\Backend\Residence;

use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\ChoiceList\ChoiceList;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

class AppartementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('residence', EntityType::class, [
            'class' => Residence::class,
            'choice_label' => 'nom_r',
            'placeholder' => 'Sélectionner la résidence'
        ])

        ->add('etage', ChoiceType::class, [
            'choices' => [ 'placeholder' => null,
                'RDC'=>0,
                'Etage 1'=>1,
                'Etage 2'=>2,
                'Etage 3'=>3,
                'Etage 4'=>4
            ],
        ])
            ->add ('numero')
            ->add('bloc', ChoiceType::class, [
                     'choices' => ['Choisissez un bloc'=>null
                     , 'A'=>'A', 'B'=>'B', 'C'=>'C', 'D'=>'D', 'E'=>'E', 'F'=>'F']
                     
        ])
            ->add('parking')
            ->add('disponible')
            ->add('image_a', FileType::class,[
                'label' => 'image_a', 'required'=>false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '10M',
                        extensions: ['png', 'jpg', 'jpeg'],
                        extensionsMessage: 'Image invalide',
                    )
                ],
            ])->add('image_a', FileType::class, array('data_class' => null,'required' => false))

            ->add('type_a', ChoiceType::class, [
            'choices' => [
                'Choisissez étage' => null,
                'S+0' => 'S+0',
                'S+1' => 'S+1',
                'S+1' => 'S+2',
                'S+3' => 'S+3',
                'S+4' => 'S+4',
                'S+5' => 'S+5'
            ],
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
