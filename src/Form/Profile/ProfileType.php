<?php

namespace App\Form\Profile;

use App\Entity\Profile\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatar', TextType::class, [
                'label' => 'Avatar URL',
                'required' => false,
            ])
            ->add('theme', ChoiceType::class, [
                'label' => 'Theme',
                'required' => false,
                'choices' => [
                    'Dark' => 0,
                    'Light' => 1,
                ],
                'placeholder' => '—',
            ])
            ->add('locale', ChoiceType::class, [
                'label' => 'Locale',
                'required' => false,
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                    'العربية' => 'ar',
                    'Bilingue FR/AR' => 'fr_ar',
                ],
                'placeholder' => '—',
            ])
            ->add('timezone', IntegerType::class, [
                'label' => 'Timezone',
                'required' => false,
            ])
            ->add('description_profile', TextareaType::class, [
                'label' => 'Description / Bio',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}
