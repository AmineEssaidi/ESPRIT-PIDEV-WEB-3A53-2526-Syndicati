<?php

namespace App\Form\Forum;

use App\Entity\Forum\Reaction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('kind', ChoiceType::class, [
                'choices' => [
                    'Like' => 'Like',
                    'Dislike' => 'Dislike',
                    'Emoji' => 'Emoji',
                    'Bookmark' => 'Bookmark',
                    'Report' => 'Report',
                ],
            ])
            ->add('emoji', TextType::class, [
                'required' => false,
            ])
            ->add('report_reason', TextType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reaction::class,
        ]);
    }
}
