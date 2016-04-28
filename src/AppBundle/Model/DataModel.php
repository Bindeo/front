<?php

namespace AppBundle\Model;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\BulkFile;
use AppBundle\Entity\File;
use AppBundle\Entity\ResultSet;
use AppBundle\Entity\Signer;
use AppBundle\Entity\User;
use Bindeo\DataModel\Exceptions;
use Bindeo\Filter\FilesFilter;
use Bindeo\Util\ApiConnection;
use Bindeo\Util\Tools;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        // Fill necessary data
        $file->setClientType('U')->setIdClient($user->getIdUser())->setName($file->getFileOrigName());

        // Add signers depending on the sign mode
        if ($file->getMode() == 'S') {
            if ($file->getSignType() == 'M' or $file->getSignType() == 'A') {
                $creator = (new Signer())->setName($user->getName())
                                         ->setIdUser($user->getIdUser())
                                         ->setEmail($user->getEmail())
                                         ->setPhone($user->getPhone())
                                         ->setCreator(1)
                                         ->setIdIdentity($user->getCurrentIdentity()->getIdIdentity());

                // Add creator to the first position of signers array
                $signers = $file->getSigners();
                array_unshift($signers, $creator);
                $file->setSigners($signers);
            }
        }

        // Save the file against the API
        $newFile = $this->api->postJson('file', $file->toArray());
        if (!$newFile->getError()) {
            // Sign the file
            $blockchain = $this->api->putJson('blockchain', $newFile->getRows()[0]->setIp($file->getIp())->toArray());
            if ($blockchain->getError()) {
                return $blockchain;
            }
            // Set user new space
            if ($user->getType() != 1) {
                $user->setStorageLeft($user->getStorageLeft() - $newFile->getRows()[0]->getSize());
            }
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
        $filter = (new FilesFilter())->setIdClient($user->getIdUser())
                                     ->setStatus($request->get('status'))
                                     ->setSpecialFilter($request->get('special'))
                                     ->setMediaType($request->get('media-type'))
                                     ->setName($request->get('name'))
                                     ->setOrder($request->get('order'))
                                     ->setPage($request->get('page'));

        $res = $this->api->getJson('files', $filter->toArray());

        return $res;
    }

    /**
     * Get a signer through a token
     *
     * @param array $params
     *
     * @return Signer
     */
    public function getSigner(array $params)
    {
        $res = $this->api->getJson('signature_signer', $params);

        // Check authorization
        if ($res->getNumRows() == 0) {
            return null;
        } else {
            // No errors
            return $res->getRows()[0];
        }
    }

    /**
     * Get a signable doc by token
     *
     * @param array            $params
     * @param SessionInterface $session
     * @param string           $baseUrl
     *
     * @return array
     */
    public function getSignableDoc(array $params, SessionInterface $session, $baseUrl)
    {
        $res = $this->api->getJson('signature', $params);

        // Check authorization
        if ($res->getError()) {
            if ($res->getError()['code'] = 403 and $res->getError()['message'] == Exceptions::FEW_PRIVILEGES) {
                $res = ['authorization' => false, 'error' => 'user'];
            } else {
                $res = ['authorization' => false, 'error' => 'token'];
            }
        } else {
            // No errors
            /** @var File $file */
            $file = $res->getRows()[0];

            if ($file->getPages() == 0) {
                $res = ['authorization' => false, 'error' => 'file'];
            } else {
                $res = ['authorization' => true];

                // Key to encode files path
                $key = random_bytes(8);
                $session->set('viewKey', $key);

                // Generate url to download file
                $data = [$file->getPath(), $file->getName()];
                $res['file'] = $file->setPath(str_replace('__FILE__',
                    Tools::safeBase64Encode(mcrypt_encrypt(MCRYPT_DES, $key, json_encode($data), MCRYPT_MODE_ECB)),
                    $baseUrl));

                // Generate pages preview urls
                $file->decodePages();
                $pages = [];
                foreach ($file->getPagesPreviews() as $page) {
                    if (is_file($page)) {
                        $data = [$page, basename($file->getName())];
                        $pages[] = str_replace('__FILE__',
                            Tools::safeBase64Encode(mcrypt_encrypt(MCRYPT_DES, $key, json_encode($data), MCRYPT_MODE_ECB)),
                            $baseUrl);
                    }
                }
                $file->setPagesPreviews($pages);
            }
        }

        return $res;
    }
}