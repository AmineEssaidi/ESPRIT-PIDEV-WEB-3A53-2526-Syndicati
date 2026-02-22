<?php
namespace App\Form\User;

use App\Entity\User\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('first_name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'First Name',
            ])
            ->add('last_name', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Last Name',
            ])
            ->add('email_user', EmailType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Email',
            ])
            ->add('telephone_user', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['placeholder' => '+216XXXXXXXX'],
            ]);

        // Password for signup and admin add (not for edit)
        if (!($options['edit'] ?? false)) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm Password'],
                'invalid_message' => 'Passwords must match.',
                'required' => true,
            ]);
        }

        // Role only for admin (not signup)
        if (!($options['signup'] ?? false)) {
            $builder->add('role_user', ChoiceType::class, [
                'choices' => [
                    'RESIDENT' => 'RESIDENT',
                    'SYNDIC' => 'SYNDIC',
                    'OWNER' => 'OWNER',
                    'ADMIN' => 'ADMIN',
                    'SUPERADMIN' => 'SUPERADMIN',
                ],
                'label' => 'Role',
                'required' => true,
            ]);
        }

        // Verified checkbox for edit and admin add
        if (($options['edit'] ?? false) || ($options['add'] ?? false)) {
            $builder->add('is_verified', CheckboxType::class, [
                'label' => 'Verified (can log in)',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'signup' => false,
            'edit' => false,
            'add' => false,
        ]);
    }
}
