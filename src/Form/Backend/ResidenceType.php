<?php

namespace App\Form\Backend;

use App\Entity\Backend\Residence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

class ResidenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_r')
            ->add('adresse')
            ->add('n_etages')
            ->add('n_blocs')
            ->add('image_r', FileType::class,[
                'label' => 'image_r', 'required'=>false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '10M',
                        extensions: ['png', 'jpg', 'jpeg'],
                        extensionsMessage: 'Image invalide',
                    )
                ],
            ])->add('image_r', FileType::class, array('data_class' => null,'required' => false))



        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Residence::class,
        ]);
    }
}
