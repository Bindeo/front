<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use AppBundle\Form\Type\PreUploadType;
use AppBundle\Form\Type\UploadFileType;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DataController extends Controller
{
    /**
     * User file library
     * @Route("/data/library", name="file_library")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function fileLibraryAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // List of files
        $files = $this->get('app.model.data')->library($user, $request);

        // If is an Ajax request
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'result' => [
                    'success' => true,
                    'html'    => $this->renderView('data/partials/file-list.html.twig', ['files' => $files])
                ]
            ]);
        } else {
            $accounts = $this->get('app.master_data')->createAccountType($request->getLocale());
            $mediaTypes = $this->get('app.master_data')->createMediaType($request->getLocale());
            $user->setTotalStorage($accounts->getRows()[$user->getType()]->getMaxStorage());

            // Fileupload layer
            $drag = $this->confirmedUpload($request, $user, false);

            // To format numbers
            $formatter = $this->get('app.locale_format');

            return $this->render('data/file-library.html.twig', [
                'drag'       => $drag,
                'mediaTypes' => $mediaTypes->getRows(),
                'files'      => $files,
                'freespace'  => $formatter->format(round($user->getStorageLeft() / 1024 / 1024, 2,
                    PHP_ROUND_HALF_DOWN)),
                'used'       => $formatter->format(round(($user->getTotalStorage() - $user->getStorageLeft()) / 1024 / 1024,
                    2, PHP_ROUND_HALF_DOWN)),
                'total'      => $formatter->format(round($user->getTotalStorage() / 1024 / 1024, 2,
                    PHP_ROUND_HALF_DOWN))
            ]);
        }
    }

    /**
     * Upload page for all users
     * @Route("/data/upload", name="file_upload")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function uploadFileAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        $response = $user->getConfirmed() ? $this->confirmedUpload($request, $user, true)
            : $this->unconfirmedUpload($request, $user);

        return $response;
    }

    /**
     * Upload page for a confirmed user
     *
     * @param Request $request
     * @param User    $user
     * @param bool    $fullPage True: render the full page, false: render only the section
     *
     * @return Response
     */
    private function confirmedUpload(Request $request, $user, $fullPage)
    {
        // Initialize the file object
        $file = (new File())->setUser($user);

        // Create form
        $user->setIdentities($this->get('app.api_connection')
                                  ->getJson('account_identities', ['idUser' => $user->getIdUser()])
                                  ->getRows());
        $form = $this->createForm(UploadFileType::class, $file);

        // Check the form
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Successful upload
                $request->getSession()->set('fileupload', 'ok');
                $res = $this->get('app.model.data')
                            ->uploadFile($user, $file->setIdUser($user->getIdUser())->setIp($request->getClientIp()));

                // Check if the file has been properly uploaded and signed
                if ($res->getError()) {
                    if ($res->getError()['code'] == 409) {
                        $error = $this->get('translator')->trans('You have already uploaded the same file');
                    } else {
                        $error = $this->get('translator')->trans('There was a problem processing the file');
                    }
                    $form->addError(new FormError($error));

                    return new JsonResponse([
                        'result' => [
                            'success' => false,
                            'form'    => $this->renderView('data/partials/file-upload-form.html.twig',
                                ['form' => $form->createView()])
                        ]
                    ]);
                } else {
                    // To format numbers
                    $formatter = $this->get('app.locale_format');

                    return new JsonResponse([
                        'result' => [
                            'success'   => true,
                            'freespace' => $formatter->format(round($user->getStorageLeft() / 1024 / 1024, 2,
                                PHP_ROUND_HALF_DOWN)),
                            'usedspace' => $formatter->format(round(($user->getTotalStorage() - $user->getStorageLeft()) / 1024 / 1024,
                                2, PHP_ROUND_HALF_DOWN)),
                            'html'      => $this->renderView('data/partials/file-upload-ok.html.twig')
                        ]
                    ]);
                }
            } else {
                if ($form->getErrors() and (!$file->getPath() or !$file->getFileOrigName())) {
                    $form->addError(new FormError($this->get('translator')->trans('You have to upload a file')));
                }

                return new JsonResponse([
                    'result' => [
                        'success' => false,
                        'form'    => $this->renderView('data/partials/file-upload-form.html.twig',
                            ['form' => $form->createView()])
                    ]
                ]);
            }
        }

        // User max filesize
        /** @var AccountType $type */
        $type = $this->get('app.master_data')->createAccountType($request->getLocale())->getRows()[$user->getType()];
        $user->setTotalStorage($type->getMaxStorage());

        return $fullPage
            ? $this->render('data/file-upload.html.twig', [
                'form'      => $form->createView(),
                'confirmed' => $user->getConfirmed(),
                'filesize'  => $type->getMaxFilesize(),
                'freespace' => $user->getStorageLeft()
            ])
            : $this->renderView('data/partials/file-upload-drag.html.twig', [
                'form'      => $form->createView(),
                'confirmed' => $user->getConfirmed(),
                'filesize'  => $type->getMaxFilesize(),
                'freespace' => $user->getStorageLeft()
            ]);
    }

    /**
     * Upload page for an unconfirmed user
     *
     * @param Request $request
     * @param User    $user
     *
     * @return Response
     */
    private function unconfirmedUpload(Request $request, $user)
    {
        $newUser = (new User())->setOldEmail($user->getEmail())
                               ->setEmail($user->getEmail())
                               ->setName($user->getName())
                               ->setIdUser($user->getIdUser());
        $form = $this->createForm(PreUploadType::class, $newUser);
        $form->handleRequest($request);

        // Form submitted
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $newUser->setIp($request->getClientIp())->setLang($user->getLang());

                // Data is valid, modify the user and send the confirm email
                $res = $this->get('app.model.data')->unconfirmedUpload($user, $newUser);

                if (isset($res['error'])) {
                    if ($res['error'][0] == '') {
                        $form->addError(new FormError($res['error'][1]));
                    } else {
                        $form->get($res['error'][0])->addError(new FormError($res['error'][1]));
                    }
                }
            }

            // Check if the form is still valid
            if ($form->isValid()) {
                return new JsonResponse([
                    'result' => [
                        'success' => true,
                        'html'    => $this->renderView('data/partials/file-preupload-ok.html.twig',
                            ['email' => $newUser->getEmail()])
                    ]
                ]);
            } else {
                return new JsonResponse([
                    'result' => [
                        'success' => false,
                        'form'    => $this->renderView('data/partials/file-preupload-form.html.twig',
                            ['form' => $form->createView()])
                    ]
                ]);
            }
        }

        return $this->render('data/file-preupload.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Upload the file temporary using fileupload
     * @Route("/ajax/private/upload-file", name="ajax_file_upload")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxUploadFileAction(Request $request)
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if (!$file) {
            return new JsonResponse(['success' => false, 'name' => 'upload_file']);
        } else {
            // Process data
            /** @var File $newFile */
            $newFile = $this->get('app.model.data')->ajaxUploadFile($file);
            if (!($newFile instanceof File)) {
                return new JsonResponse(['success' => false, 'name' => 'upload_file', 'error' => $newFile]);
            }

            $size = $newFile->getSize();
            if ($size > 1000000) {
                $size = round($size / 1000000, 2) . ' MB';
            } else {
                $size = round($size / 1000, 2) . ' KB';
            }

            return new JsonResponse([
                'success' => true,
                'name'    => 'upload_file',
                'path'    => $newFile->getPath(),
                'html'    => $this->renderView('data/partials/file-uploaded.html.twig',
                    ['name' => $newFile->getFileOrigName(), 'size' => $size])
            ]);
        }
    }

    /**
     * Render the file upload result
     * @Route("/data/upload/result", name="file_upload_res")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function uploadFileResultAction(Request $request)
    {
        if (!$request->getSession()->has('fileupload')) {
            // Invalid access
            return new RedirectResponse('/');
        } elseif ($request->getSession()->get('fileupload') == 'ok') {
            // Correct upload
            $request->getSession()->remove('fileupload');

            return $this->render('data/file-upload-ok.html.twig');
        } else {
            // Validate email
            $request->getSession()->remove('fileupload');

            return $this->render('data/file-upload-preok.html.twig',
                ['email' => $request->getSession()->get('fileupload')]);
        }
    }
}