<?php

namespace AppBundle\Model;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\BulkFile;
use AppBundle\Entity\File;
use AppBundle\Entity\ResultSet;
use AppBundle\Entity\User;
use Bindeo\Filter\FilesFilter;
use Bindeo\Util\ApiConnection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;

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
     * @param MasterDataFactory     $masterData
     * @param ApiConnection         $api
     * @param TokenStorageInterface $tokenStorage
     * @param TranslatorInterface   $translator
     * @param string                $filesConf
     */
    public function __construct(
        MasterDataFactory $masterData,
        ApiConnection $api,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
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
     * Upload temporary bulk files
     *
     * @param UploadedFile $file
     *
     * @return BulkFile
     */
    public function uploadTmpBulkFile(UploadedFile $file)
    {
        // Move the file to a temp folder
        return (new BulkFile())->setPath($file->move($this->filesConf['files_tmp_folder'])->getRealPath());
    }

    /**
     * Save and sign a file
     *
     * @param User $user
     * @param File $file
     *
     * @return ResultSet
     */
    public function uploadFile(User $user, File $file)
    {
        // Save the file against the API
        $newFile = $this->api->postJson('file', $file->toArray());
        if (!$newFile->getError()) {
            // Sign the file
            $blockchain = $this->api->putJson('blockchain', $newFile->getRows()[0]->setIp($file->getIp())->toArray());
            if ($blockchain->getError()) {
                return $blockchain;
            }
            // Set user new space
            $user->setStorageLeft($user->getStorageLeft() - $newFile->getRows()[0]->getSize());
        }

        return $newFile;
    }

    /**
     * Get a list of files from the user
     *
     * @param User    $user
     * @param Request $request
     *
     * @return ResultSet
     */
    public function library($user, Request $request)
    {
        // Instantiate files filter
        $filter = (new FilesFilter())->setIdUser($user->getIdUser())
                                     ->setStatus($request->get('status'))
                                     ->setSpecialFilter($request->get('special'))
                                     ->setMediaType($request->get('media-type'))
                                     ->setFileType($request->get('file-type'))
                                     ->setName($request->get('name'))
                                     ->setOrder($request->get('order'))
                                     ->setPage($request->get('page'));

        $res = $this->api->getJson('files', $filter->toArray());

        return $res;
    }
}