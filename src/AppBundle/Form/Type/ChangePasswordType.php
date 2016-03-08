<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('password', RepeatedType::class, [
            'type' => PasswordType::class
        ]);

        // Generate a custom select with user data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Get the user from the file object
            if ($event->getData()->getIdUser()) {
                $form->add('oldPassword', PasswordType::class);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\User',
            'validation_groups' => function (FormInterface $form) {
                /** @var User $data */
                $data = $form->getData();
                $groups = ['change-password'];

                // If the user has changed his email, we add the change-email validation group
                if ($data->getIdUser()) {
                    $groups[] = 'change-password-private';
                }

                return $groups;
            }
        ]);
    }
}