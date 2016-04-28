<?php

namespace AppBundle\Form\Type;

use AppBundle\Entity\Signer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SignerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class, ['label' => false]);

        // If we are signing, we add code and document fields
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Get the user from the file object
            if ($event->getData() instanceof Signer) {
                // Group sign-file
                $form->add('code', TextType::class, ['label' => false]);
                $form->add('document', TextType::class, ['label' => false]);
            } else {
                // Group upload-file
                $form->add('email', EmailType::class, ['label' => false]);
                $form->add('phone', TextType::class, ['label' => false, 'required' => false]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\Signer',
            'validation_groups' => function (FormInterface $form) {
                /** @var Signer $signer */
                $signer = $form->getData();
                if ($signer->getToken()) {
                    $groups = ['sign-file'];
                } else {
                    $groups = ['upload-file'];
                }

                return $groups;
            }
        ]);
    }
}