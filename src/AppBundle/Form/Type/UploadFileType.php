<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Form info
        $builder->add('path', HiddenType::class)
                ->add('fileOrigName', HiddenType::class)
                ->add('mode', HiddenType::class)
                ->add('signType', HiddenType::class)
                ->add('signers', CollectionType::class, [
                    'entry_type'   => SignerType::class,
                    'label'        => false,
                    'allow_add'    => true,
                    'allow_delete' => true
                ]);
        /*
        // Generate a custom select with user data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Get the user from the file object
            $user = $event->getData()->getUser();

            $identities = $user->getIdentities();
            $choices = [];
            if ($identities) {
                foreach ($identities as $identity) {
                    $choices[$identity->getFullName()] = $identity->getIdIdentity();
                }
            }

            $form->add('idClient', ChoiceType::class, [
                'choices' => $choices,
                'label'   => 'Owner identity'
            ]);
        });
        */
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\File',
            'validation_groups' => ['upload-file']
        ]);
    }
}