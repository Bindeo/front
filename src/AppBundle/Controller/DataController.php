<?php

namespace AppBundle\Controller;

use AppBundle\Entity\AccountType;
use AppBundle\Entity\File;
use AppBundle\Entity\User;
use AppBundle\Form\Type\SignerType;
use AppBundle\Form\Type\UploadFileType;
use Bindeo\Util\Tools;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use mikehaertl\wkhtmlto\Pdf;

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
    public function libraryAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // List of processes
        $processes = $this->get('app.model.data')->library($user, $request);

        // If is an Ajax request
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'result' => [
                    'success' => true,
                    'html'    => $this->renderView('data/partials/processes-list.html.twig',
                        ['processes' => $processes, 'user' => $user])
                ]
            ]);
        } else {
            $accounts = $this->get('app.master_data')->createAccountTypes($request->getLocale());
            $processesStatus = $this->get('app.master_data')->createProcessesStatus($request->getLocale());
            $user->setTotalStorage($accounts->getRows()[$user->getType()]->getMaxStorage());

            // Fileupload layer
            $drag = $this->confirmedUpload($request, $user, false);

            // To format numbers
            $formatter = $this->get('app.locale_format');

            // Data to render
            $data = [
                'drag'            => $drag,
                'processesStatus' => $processesStatus->getRows(),
                'processes'       => $processes,
                'user'            => $user,
                'freespace'       => $formatter->format(round($user->getStorageLeft() / 1024 / 1024, 2,
                    PHP_ROUND_HALF_DOWN)),
                'used'            => $formatter->format(round(($user->getTotalStorage() - $user->getStorageLeft()) /
                                                              1024 / 1024, 2, PHP_ROUND_HALF_DOWN)),
                'total'           => $formatter->format(round($user->getTotalStorage() / 1024 / 1024, 2,
                    PHP_ROUND_HALF_DOWN))
            ];

            // If we have any message to show at loading, we take it from session
            if ($request->getSession()->has('message')) {
                $data['message'] = $request->getSession()->get('message');
                $request->getSession()->remove('message');
            }

            return $this->render('data/library.html.twig', $data);
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

        // Only confirmed users could upload files
        $response = $this->confirmedUpload($request, $user, true);

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
        $form = $this->createForm(UploadFileType::class, $file);

        // Check the form
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Successful upload
                $request->getSession()->set('fileupload', 'ok');
                $res = $this->get('app.model.data')->uploadFile($user, $file->setIp($request->getClientIp()));

                // Check if the file has been properly uploaded and signed
                if ($res->getError()) {
                    if ($res->getError()['code'] == 409) {
                        $error = $this->get('translator')->trans('You have already uploaded the same file');
                    } else {
                        $error = $this->get('translator')
                                      ->trans('There was a problem processing the file, please try it later');
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
                    // Depends on the mode
                    if ($file->getMode() == 'S' and ($file->getSignType() == 'M' or $file->getSignType() == 'A')) {
                        // If user uploaded a file to sign and he wants to sign too, we redirect him to the signature page
                        return new JsonResponse([
                            'result' => [
                                'success'  => true,
                                'redirect' => $this->generateUrl('file_signature',
                                    ['token' => 's' . $res->getRows()[0]->getIdBulk()])
                            ]
                        ]);
                    } else {
                        // To format numbers
                        $formatter = $this->get('app.locale_format');

                        $trans = $this->get('translator');

                        return new JsonResponse([
                            'result' => [
                                'success'   => true,
                                'freespace' => $formatter->format(round($user->getStorageLeft() / 1024 / 1024, 2,
                                    PHP_ROUND_HALF_DOWN)),
                                'usedspace' => $formatter->format(round(($user->getTotalStorage() -
                                                                         $user->getStorageLeft()) / 1024 / 1024, 2,
                                    PHP_ROUND_HALF_DOWN)),
                                'message'   => $trans->trans('Your file is being notarized') . ', ' .
                                               $trans->trans('soon you will receive a confirmation email with the certificate attached')
                            ]
                        ]);
                    }
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
        $type = $this->get('app.master_data')->createAccountTypes($request->getLocale())->getRows()[$user->getType()];
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
            $newFile = $this->get('app.model.data')->ajaxUploadFile($file, $request->getClientIp());
            if (!($newFile instanceof File)) {
                return new JsonResponse(['success' => false, 'name' => 'upload_file', 'error' => $newFile]);
            }

            $size = $newFile->getSize();
            if ($size > 1000000) {
                $size = round($size / 1000000, 2) . ' MB';
            } else {
                $size = round($size / 1000, 2) . ' KB';
            }

            // We can return the object or a rendered html
            if ($request->get('norender')) {
                return new JsonResponse([
                    'success'  => true,
                    'name'     => 'upload_file',
                    'path'     => $newFile->getPath(),
                    'filename' => $newFile->getFileOrigName(),
                    'country'  => $newFile->getIp() ? mb_strtolower($newFile->getIp()) : 'es'
                ]);
            } else {
                return new JsonResponse([
                    'success' => true,
                    'name'    => 'upload_file',
                    'path'    => $newFile->getPath(),
                    'html'    => $this->renderView('data/partials/file-uploaded.html.twig', [
                        'name'    => $newFile->getFileOrigName(),
                        'size'    => $size,
                        'country' => $newFile->getIp() ? mb_strtolower($newFile->getIp()) : 'es'
                    ])
                ]);
            }
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
        $trans = $this->get('translator');

        if ($request->getSession()->has('fileupload') and $request->getSession()->get('fileupload') == 'ok') {
            // Correct upload
            $request->getSession()->remove('fileupload');

            // Set proper message in session
            $request->getSession()->set('message', [
                'res'   => true,
                'value' => $trans->trans('Your file is being notarized') . ', ' .
                           $trans->trans('soon you will receive a confirmation email with the certificate attached')
            ]);
        } else {
            // Set proper message in session
            $request->getSession()->set('message', [
                'res'   => false,
                'value' => $trans->trans('There was a problem processing the file, please try it later')
            ]);
        }

        return new RedirectResponse('/');
    }

    /**
     * View a signable document through user token
     * @Route("/data/signature/{token}", name="file_signature")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function getSignableDocAction(Request $request)
    {
        // If user is logged, send user id too
        $params = ['token' => $request->get('token')];

        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $params['idUser'] = $this->getUser()->getIdUser();
        }

        // Build signer
        $signer = $this->get('app.model.data')->getSigner($params);

        // If we haven't got signer, we return the error
        if (!$signer) {
            return $this->render('data/signable-doc.html.twig',
                ['authorization' => false, 'error' => is_numeric($params['token']) ? 'user' : 'token']);
        }

        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            /** @var User $user */
            $user = $this->getUser();
            $identity = $user->getCurrentIdentity();

            $signer->setIdUser($user->getIdUser())
                   ->setIdIdentity($identity->getIdIdentity())
                   ->setName($identity->getName())
                   ->setEmail($identity->getValue())
                   ->setDocument($identity->getDocument())
                   ->setLang($user->getLang());
        } else {
            $signer->setLang($request->getLocale());
        }

        $form = $this->createForm(SignerType::class, $signer);
        $form->handleRequest($request);

        // Form submitted
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Send pin code
                $signer->setIp($request->getClientIp());
                $res = $this->get('app.api_connection')
                            ->putJson('signature', $signer->setIp($request->getClientIp())->toArray());

                // If there was an error
                if ($res->getError()) {
                    if ($res->getError()['code'] = 403) {
                        $form->addError(new FormError($this->get('translator')
                                                           ->trans('Your PIN code has expired, please click again in sign document button to receive a new one')));
                    } else {
                        $form->addError(new FormError($this->get('translator')
                                                           ->trans('There was a problem signing the document, please try again later')));
                    }

                    return new JsonResponse([
                        'result' => [
                            'success' => false,
                            'form'    => $this->renderView('data/partials/sign-file-form.html.twig',
                                ['form' => $form->createView(), 'signer' => $signer])
                        ]
                    ]);
                } else {
                    $trans = $this->get('translator');

                    // Set proper message in session
                    $request->getSession()->set('message', [
                        'res'   => true,
                        'value' => $trans->trans('Your signature is completed, please wait until all signers complete their signatures and certificate is generated') .
                                   ', ' .
                                   $trans->trans('soon you will receive a confirmation email with the certificate attached')
                    ]);

                    return new JsonResponse(['result' => ['success' => true, 'redirect' => '/']]);
                }
            } else {
                return new JsonResponse([
                    'result' => [
                        'success' => false,
                        'form'    => $this->renderView('data/partials/sign-file-form.html.twig',
                            ['form' => $form->createView(), 'signer' => $signer])
                    ]
                ]);
            }
        }

        // Get document
        $res = $this->get('app.model.data')
                    ->getSignableDoc($params, $request->getSession(),
                        $this->generateUrl('file_view', ['file' => '__FILE__']));

        // Renderize form
        $res['form'] = $form->createView();
        $res['signer'] = $signer;

        return $this->render('data/signable-doc.html.twig', $res);
    }

    /**
     * Request a signature code generation
     * @Route("/ajax/generate-sign-code/{token}", name="ajax_generate_sign_code")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxGenerateCodeAction(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            // Params to send
            $params = [
                'token' => $request->get('token'),
                'lang'  => $request->getLocale(),
                'ip'    => $request->getClientIp()
            ];

            // If user is logged, we send user too
            if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                $params['idUser'] = $this->getUser()->getIdUser();
            }

            $this->get('app.api_connection')->getJson('signature_code', $params);

            return new Response();
        } else {
            throw $this->createNotFoundException('Url not found');
        }
    }

    /**
     * Download a file encoded with key
     * @Route("/data/view/{file}", name="file_view")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function viewFileAction(Request $request)
    {
        // Use session key
        $key = $request->getSession()->get('viewKey');

        // Decode url
        $file = trim(mcrypt_decrypt(MCRYPT_DES, $key, Tools::safeBase64Decode($request->get('file')), MCRYPT_MODE_ECB));
        list($filePath, $filename) = json_decode($file);

        if (!$filePath or !is_file($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        // Download file
        $response = new BinaryFileResponse($filePath);
        $response->trustXSendfileTypeHeader();
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }

    /**
     * Generate a notarization certificate
     * @Route("/data/document/generate-certificate", name="generate_notarization_certificate")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function generateNotarizeCertAction(Request $request)
    {
        $res = $this->get('app.model.data')->notarizationCertificate($request->get('t'), $this->getUser()->getIdUser());

        $res['lang'] = $request->getLocale() == 'es_ES' ? 'ES' : 'EN';
        $res['baseUrl'] = $request->getSchemeAndHttpHost();
        $res['type'] = 'notarization';

        if (!$res['authorization']) {
            return $this->render('data/certificate.html.twig', $res);
        }

        // Rendering only body
        if ($request->get('m') == 'html') {
            return $this->render('data/certificate-full.html.twig', $res);
        } else {
            // Generate PDF with the certificate
            $pdf = new Pdf([
                'zoom'          => '0.85',
                'margin-left'   => '0px',
                'margin-right'  => '0px',
                'margin-top'    => '750px',
                'margin-bottom' => '450px',
                'header-html'   => $this->renderView('data/certificate-header.html.twig', $res),
                'footer-html'   => $this->renderView('data/certificate-footer.html.twig', $res)
            ]);
            $pdf->addPage($this->renderView('data/certificate.html.twig', $res));

            // Send file to the browser
            $pdf->send('Bindeo_notarization_' . $res[$res['type']]->getBlockchain()->getHash() . '.pdf');
        }
    }

    /**
     * Generate a signature certificate
     * @Route("/data/signature/generate-certificate", name="generate_signature_certificate")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function generateSignCertAction(Request $request)
    {
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $idUser = $this->getUser()->getIdUser();
            $userType = 'U';
        } elseif ($request->get('u') and $request->get('ut') and $this->getParameter('secret') == $request->get('s')) {
            $idUser = $request->get('u');
            $userType = $request->get('ut');
        } else {
            $res = ['authorization' => false];
        }

        // Get element
        if (!isset($res)) {
            $res = $this->get('app.model.data')->signatureCertificate($request->get('t'), $userType, $idUser);
        }

        $res['lang'] = $request->getLocale() == 'es_ES' ? 'ES' : 'EN';
        $res['baseUrl'] = $request->getSchemeAndHttpHost();
        $res['type'] = 'signature';

        if (!$res['authorization']) {
            return $this->render('data/certificate.html.twig', $res);
        }

        // Rendering only body
        if ($request->get('m') == 'html') {
            return $this->render('data/certificate-full.html.twig', $res);
        } else {
            // Generate PDF with the certificate
            $pdf = new Pdf([
                'zoom'          => '0.85',
                'margin-left'   => '0px',
                'margin-right'  => '0px',
                'margin-top'    => '750px',
                'margin-bottom' => '450px',
                'header-html'   => $this->renderView('data/certificate-header.html.twig', $res),
                'footer-html'   => $this->renderView('data/certificate-footer.html.twig', $res)
            ]);
            $pdf->addPage($this->renderView('data/certificate.html.twig', $res));

            // Send file to the browser
            $pdf->send('Bindeo_signature_' . $res[$res['type']]->getBulk()->getExternalId() . '.pdf');
        }
    }

    /**
     * Check an email or phone field to validate it
     * @Route("/ajax/public/check-field", name="ajax_check_field")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxCheckFieldAction(Request $request)
    {
        // Validate field
        $valid = $this->get('app.model.data')
                      ->checkField($request->get('type'), $request->get('value'), $this->get('validator'));

        return new JsonResponse(['valid' => $valid]);
    }
}