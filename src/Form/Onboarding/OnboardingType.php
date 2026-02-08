<?php

namespace App\Form\Onboarding;

use App\Entity\Onboarding\Onboarding;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OnboardingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('selectedLocale', ChoiceType::class, [
                'label' => 'Locale',
                'choices' => [
                    'Français' => 'fr',
                    'English' => 'en',
                    'العربية' => 'ar',
                    'Bilingue FR/AR' => 'fr_ar',
                ],
            ])
            ->add('selectedTheme', ChoiceType::class, [
                'label' => 'Theme',
                'choices' => [
                    'Dark' => 'dark',
                    'Light' => 'light',
                ],
            ])
            ->add('suggestions', TextareaType::class, [
                'label' => 'Suggestions',
                'required' => false,
            ]);

        $usePrefsFromRequest = ($options['profile_edit'] ?? false) || ($options['use_prefs_from_request'] ?? false);
        if (!$usePrefsFromRequest) {
            $builder->add('selectedPreferences', TextareaType::class, [
                'label' => 'Preferences (JSON)',
                'required' => false,
            ]);
            $builder->get('selectedPreferences')->addModelTransformer(new CallbackTransformer(
                fn (?array $arr) => $arr !== null && $arr !== [] ? json_encode($arr, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) : '',
                fn (?string $str) => $str !== null && $str !== '' ? (json_decode($str, true) ?: []) : []
            ));
        }

        if ($options['admin_edit'] ?? false) {
            $builder
                ->add('step', IntegerType::class, ['label' => 'Step', 'required' => true])
                ->add('completed', CheckboxType::class, ['label' => 'Completed', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Onboarding::class,
            'admin_edit' => false,
            'profile_edit' => false,
            'use_prefs_from_request' => false,
        ]);
    }
}
