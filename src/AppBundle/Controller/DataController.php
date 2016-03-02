<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use AppBundle\Form\Type\UploadFileType;
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
     * @Route("/data/library", name="file_library")
     * @param Request $request
     *
     * @return Response
     */
    public function fileLibraryAction(Request $request)
    {
        return $this->render('data/file-library.html.twig');
    }

    /**
     * @Route("/data/upload", name="file_upload")
     * @param Request $request
     *
     * @return Response
     */
    public function uploadFileAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();
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
                $res = $this->get('app.model.data')->uploadFile($file->setIdUser($user->getIdUser())
                                                                     ->setIp($request->getClientIp()));

                // Check if the file has been properly uploaded and signed
                if ($res->getError()) {
                    if($res->getError()['code'] == 409) {
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
                    return new JsonResponse([
                        'result' => [
                            'success'  => true,
                            'html'     => $this->renderView('data/partials/file-upload-ok.html.twig')
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

        return $this->render('data/file-upload.html.twig', [
            'form'      => $form->createView(),
            'filesize'  => $type->getMaxFilesize(),
            'freespace' => $user->getStorageLeft()
        ]);
    }

    /**
     * @Route("/ajax/private/upload-file", name="ajax_file_upload")
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
     * @Route("/data/upload/result", name="file_upload_res")
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