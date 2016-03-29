<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BulkFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Values for demo
        $types = ['Type' => '', 'Diploma' => 1, 'Derechos de autor' => 2];
        $qualifications = [
            'Qualification'    => '',
            '(Does not apply)' => 0,
            'Sobresaliente'    => 'A',
            'Notable'          => 'B',
            'Bien'             => 'C',
            'Suficiente'       => 'D'
        ];

        $format = 'dd/MM/yyyy';

        $builder->add('fileType', ChoiceType::class, ['choices' => $types, 'label' => false])
                ->add('uniqueId', TextType::class, ['label' => false])
                ->add('fullName', TextType::class, ['label' => false])
                ->add('fileDate', DateType::class, [
                    'label'           => false,
                    'widget'          => 'single_text',
                    'format'          => $format,
                    'invalid_message' => 'Valid format ' . $format
                ])
                ->add('qualification', ChoiceType::class, ['choices' => $qualifications, 'label' => false])
                ->add('path', HiddenType::class)
                ->add('fileOrigName', HiddenType::class);

        // Generate a custom select with user data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();

            // Values for demo
            $signs = ['Signature' => '', 'ISDI' => 1, 'Internet Academi' => 2];
            $content = [
                'Control content'                            => '',
                '(Does not apply)'                           => 0,
                'Máster Community Manager y Social Media'    => 1,
                'Indicadores de éxito en las Redes Sociales' => 2,
                'MIB España - Máster en Internet Business'   => 3,
                'MIB México - Máster en Internet Business'   => 4,
                'MDA - Máster en Digital Analytics'          => 5
            ];

            $form->add('idSign', ChoiceType::class, [
                'choices' => $signs,
                'label'   => false
            ])->add('idContent', ChoiceType::class, [
                'choices' => $content,
                'label'   => false
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'        => 'AppBundle\Entity\BulkFile',
            'validation_groups' => ['create-bulk']
        ]);
    }
}