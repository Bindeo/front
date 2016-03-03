<?php

namespace AppBundle\Model;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use Bindeo\DataModel\Exceptions;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\DataCollectorTranslator;

class DataModel
{
    private $masterData;
    private $api;
    /** @var User $user */
    private $user;
    private $translator;
    private $filesConf;

    /**
     * DataModel constructor.
     *
     * @param MasterDataFactory       $masterData
     * @param ApiConnection           $api
     * @param TokenStorageInterface   $tokenStorage
     * @param DataCollectorTranslator $translator
     * @param string                  $filesConf
     */
    public function __construct(
        MasterDataFactory $masterData,
        ApiConnection $api,
        TokenStorageInterface $tokenStorage,
        DataCollectorTranslator $translator,
        $filesConf
    ) {
        $this->masterData = $masterData;
        $this->api = $api;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->translator = $translator;
        $this->filesConf = $filesConf;
    }

    /**
     * Process file uploaded
     *
     * @param UploadedFile $file
     *
     * @return File|string
     */
    public function ajaxUploadFile(UploadedFile $file)
    {
        // We need to check that the file satisfies user max file size and free space
        $size = $file->getClientSize();

        // If the user is not admin
        if ($this->user->getType() != User::ROLE_ADMIN) {
            // User max filesize
            /** @var AccountType $type */
            $type = $this->masterData->createAccountType($this->user->getLang())->getRows()[$this->user->getType()];

            // Filesize for users no admins
            if ($size > $type->getMaxFilesize() * 1024) {
                $error = 'filesize';
            } elseif ($size > $this->user->getStorageLeft() * 1024) {
                $error = 'freespace';
            }
        }

        if (isset($error)) {
            return $error;
        } else {
            // Move the file to a temp folder to manage its data later and returns the new File
            return (new File)->setFileOrigName($file->getClientOriginalName())
                             ->setPath($file->move($this->filesConf['files_tmp_folder'])->getRealPath())
                             ->setSize($size);
        }
    }

    /**
     * Save and sign a file
     *
     * @param File $file
     *
     * @return \AppBundle\Entity\ResultSet
     */
    public function uploadFile(File $file)
    {
        // Save the file against the API
        $res = $this->api->postJson('file', $file->toArray());
        if ($res->getError()) {
            return $res;
        }

        // Sign the file
        $res = $this->api->putJson('blockchain', $res->getRows()[0]->setIp($file->getIp())->toArray());

        return $res;
    }

    /**
     * Change the necessary user data
     *
     * @param User $user
     * @param User $newUser
     * @return array
     */
    public function unconfirmedUpload(User $user, User $newUser)
    {
        // User has changed his email
        if ($newUser->getEmail() != $newUser->getOldEmail()) {
            // Change the email against the api
            $res = $this->api->putJson('account_email', $newUser->toArray());

            // Check errors
            if ($res->getError()) {
                if ($res->getError()['message'] == Exceptions::INCORRECT_PASSWORD) {
                    return ['error' => ['password', $this->translator->trans('Your password is not correct')]];
                } elseif ($res->getError()['message'] == Exceptions::DUPLICATED_KEY) {
                    return ['error' => ['email', $this->translator->trans('The email is already used')]];
                } else {
                    return ['error' => ['', $this->translator->trans('There was a problem with your request, please try it later')]];
                }
            }
        } else {
            // Resend the validation token
            $this->api->getJson('account_token', $newUser->toArray());
        }

        // User has changed his name
        if ($newUser->getName() != $user->getName()) {
            // Change the name against the api
            $res = $this->api->putJson('account', $newUser->toArray());

            // Check errors
            if ($res->getError()) {
                return ['error' => ['', $this->translator->trans('There was a problem with your request, please try it later')]];
            }

            // Set the new name in the logged user
            $user->setName($newUser->getName());
        }

        return ['success' => true];
    }
}