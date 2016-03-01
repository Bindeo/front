<?php

namespace AppBundle\Form\Type;

use AppBundle\Model\MasterDataFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadFileType extends AbstractType
{
    /**
     * @var \AppBundle\Entity\ResultSet
     */
    private $types;

    public function __construct(Session $session, MasterDataFactory $masterData)
    {
        $this->types = $masterData->createFileType($session->get('_locale'));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // File types master data
        $types = [];
        foreach ($this->types->getRows() as $row) {
            $types[$row->getName()] = $row->getIdType();
        }

        $builder->add('name', TextType::class)
                ->add('idType', ChoiceType::class, ['choices' => $types, 'label' => 'Type of asset'])
                ->add('path', HiddenType::class);

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

            $form->add('idUser', ChoiceType::class, [
                'choices' => $choices,
                'label'   => 'Owner identity'
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class'        => 'AppBundle\Entity\File',
            'validation_groups' => array('upload-file')
        ));
    }
}