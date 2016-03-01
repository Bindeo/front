<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use AppBundle\Form\Type\UploadFileType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        // Initialize the file object
        $file = new File();

        // Logged user
        /** @var User $user */
        $user = $this->getUser();
        $file->setUser($user);

        // Create form
        $user->setIdentities($this->get('app.api_connection')
                                  ->getJson('account_identities', ['idUser' => $user->getIdUser()])
                                  ->getRows());
        $form = $this->createForm(UploadFileType::class, $file);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                return new JsonResponse(['result' => ['success' => true]]);
            } else {
                if ($form->getErrors() and !$file->getPath()) {
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

        return $this->render('data/file-upload.html.twig',
            ['form' => $form->createView(), 'filesize' => $type->getMaxFilesize()]);
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
            $newFile = $file->move($this->getParameter('files_tmp_folder'));

            $size = $file->getClientSize();
            if ($size > 1000000) {
                $size = round($size / 1000000, 2) . ' MB';
            } else {
                $size = round($size / 1000, 2) . ' KB';
            }

            return new JsonResponse([
                'success' => true,
                'name'    => 'upload_file',
                'path'    => $newFile->getRealPath(),
                'html'    => $this->renderView('data/partials/file-uploaded.html.twig',
                    ['name' => $file->getClientOriginalName(), 'size' => $size])
            ]);
        }
    }
}