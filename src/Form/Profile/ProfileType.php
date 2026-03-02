<?php

namespace App\Form\Profile;

use App\Entity\Profile\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('avatarFile', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Upload Avatar',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF, WebP)',
                    ])
                ],
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
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => [
                    'placeholder' => '+216XXXXXXXX',
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'mapped' => false,
                'required' => false,
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => false,
                'mapped' => false,
                'first_options' => ['label' => 'New Password'],
                'second_options' => ['label' => 'Repeat Password'],
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}
