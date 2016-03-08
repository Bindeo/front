<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class)
                ->add('email', EmailType::class)
                ->add('password', PasswordType::class, ['required' => false, 'label' => 'Confirm your password']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\User',
            'validation_groups' => function(FormInterface $form) {
                /** @var User $data */
                $data = $form->getData();
                $groups = ['pre-upload'];

                // If the user has changed his email, we add the change-email validation group
                if ($data->getEmail() and $data->getOldEmail() and $data->getEmail() != $data->getOldEmail()) {
                    $groups[] = 'change-email';
                }

                return $groups;
            }
        ]);
    }
}