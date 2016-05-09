<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\UserIdentity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangeIdentityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class)
                ->add('value', EmailType::class, ['label' => 'Email'])
                ->add('document', TextType::class, ['label' => 'National Identity Number'])
                ->add('password', PasswordType::class, ['required' => false, 'label' => 'Confirm your password']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\UserIdentity',
            'validation_groups' => function(FormInterface $form) {
                /** @var UserIdentity $data */
                $data = $form->getData();
                $groups = ['identity'];

                // If the user has changed his email, we add the change-email validation group
                if ($data->getValue() and $data->getOldValue() and $data->getValue() != $data->getOldValue()) {
                    $groups[] = 'change-email';
                }

                return $groups;
            }
        ]);
    }
}