<?php

namespace AppBundle\Model;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DataModel
{
    private $masterData;
    private $api;
    /** @var User $user */
    private $user;
    private $filesConf;

    /**
     * DataModel constructor.
     *
     * @param MasterDataFactory     $masterData
     * @param ApiConnection         $api
     * @param TokenStorageInterface $tokenStorage
     * @param string                $filesConf
     */
    public function __construct(
        MasterDataFactory $masterData,
        ApiConnection $api,
        TokenStorageInterface $tokenStorage,
        $filesConf
    ) {
        $this->masterData = $masterData;
        $this->api = $api;
        $this->user = $tokenStorage->getToken()->getUser();
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
        $file->setUser(null);

        // Save the file against the API
        $res = $this->api->postJson('file', $file->toArray());
        if($res->getError()) {
            return $res;
        }

        return $res;
    }
}