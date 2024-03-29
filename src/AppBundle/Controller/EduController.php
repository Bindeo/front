<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BulkFile;
use AppBundle\Entity\BulkTransaction;
use AppBundle\Form\Type\BulkTransactionType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EduController extends Controller
{
    /**
     * Home page for education
     * @Route("/edu/isdi", name="edu_home")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function homeAction(Request $request)
    {
        // Check if we have an identifier
        if ($request->isXmlHttpRequest() and ($request->get('uniqueId') or $request->files->get('file'))) {
            return $this->forward('AppBundle:Edu:verify');
        }

        return $this->render('edu/home.html.twig');
    }

    public function verifyAction(Request $request)
    {
        $id = $request->get('uniqueId');
        $tmpFile = $request->files->get('file');

        // Check against the api
        $file = $id ? new BulkFile(['uniqueId' => $id]) : $this->get('app.model.data')->uploadTmpBulkFile($tmpFile);
        $res = $this->get('app.api_connection')->getJson('bulk_verify', $file->toArray());

        // Check if we have found the file
        if ($res->getNumRows() == 0) {
            // For making results more elegant, if user uploaded a file and we didn't find it, we calculate the hash
            if (isset($tmpFile)) {
                $file->setHash(hash_file('sha256', $file->getPath()));
            }

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'result' => [
                        'success' => true,
                        'html'    => $this->renderView('edu/partials/verify.html.twig',
                            ['result' => 'notvalid', 'file' => $file])
                    ]
                ]);
            } else {
                return $this->render('edu/verify.html.twig', [
                    'result' => 'notvalid',
                    'file'   => $file
                ]);
            }
        } else {
            /** @var BulkFile $file */
            $file = $res->getRows()[0];
            // Get blockchain transaction information
            $res = $this->get('app.api_connection')
                        ->getJson('blockchain', ['transaction' => $file->getTransaction(), 'mode' => 'full']);

            // Transaction doesn't exist
            if ($res->getNumRows() == 0) {
                return new JsonResponse([
                    'result' => [
                        'success' => true,
                        'html'    => $this->renderView('edu/partials/verify.html.twig',
                            ['result' => 'notvalid', 'file' => $file])
                    ]
                ]);
            }

            // Types defined for demo
            $types = [1 => 'Diploma', 2 => 'Derechos de autor'];
            $contents = [
                1 => 'Máster Community Manager y Social Media',
                2 => 'Indicadores de éxito en las Redes Sociales',
                3 => 'MIB España - Máster en Internet Business',
                4 => 'MIB México - Máster en Internet Business',
                5 => 'MDA - Máster en Digital Analytics'
            ];
            $qualifications = [
                'A' => 'Sobresaliente',
                'B' => 'Notable',
                'C' => 'Bien',
                'D' => 'Suficiente'
            ];

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'result' => [
                        'success' => true,
                        'html'    => $this->renderView('edu/partials/verify.html.twig', [
                            'result'         => 'valid',
                            'file'           => $file,
                            'blockchain'     => $res->getRows()[0],
                            'types'          => $types,
                            'contents'       => $contents,
                            'qualifications' => $qualifications
                        ])
                    ]
                ]);
            } else {
                return $this->render('edu/verify.html.twig', [
                    'result'         => 'valid',
                    'file'           => $file,
                    'blockchain'     => $res->getRows()[0],
                    'types'          => $types,
                    'contents'       => $contents,
                    'qualifications' => $qualifications
                ]);
            }
        }
    }

    /**
     * Create new bulk transaction
     * @Route("/edu/isdi/notarize", name="edu_notarize")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function createBulkTransactionAction(Request $request)
    {
        $bulkTransaction = new BulkTransaction();

        // Create and fill the form
        $form = $this->createForm(BulkTransactionType::class, $bulkTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // ISDI client, temporary for this demo version
                $bulkTransaction->setClientType('C')
                                ->setIdClient(5)
                                ->setIp($request->getClientIp())
                                ->setType('Smart Certificates')
                                ->setElementsType('F')
                                ->setExternalId('ISDI_' . md5(time()));
                // Send to the api
                $params = $bulkTransaction->toArray();
                $params['mode'] = 'create';
                $res = $this->get('app.api_connection')->postJson('bulk_transaction', $params);

                // Check if the bulk transaction has been properly created and signed
                if ($res->getError()) {
                    if ($res->getError()['code'] == 409) {
                        $error = $this->get('translator')->trans('You have already uploaded the same collection');
                    } else {
                        $error = $this->get('translator')->trans('There was a problem processing the files');
                    }
                    $form->addError(new FormError($error));

                    // Return the error
                    return new JsonResponse([
                        'result' => [
                            'success' => false,
                            'form'    => $this->renderView('edu/partials/bulk-transaction-form.html.twig',
                                ['form' => $form->createView()])
                        ]
                    ]);
                } else {
                    $trans = $this->get('translator');

                    return new JsonResponse([
                        'result' => [
                            'success' => true,
                            'message' => $trans->trans('Your collection is being notarized') . ', ' .
                                         $trans->trans('soon you will receive a confirmation email with the certificate attached')
                        ]
                    ]);
                }
            } else {
                // There was an error
                return new JsonResponse([
                    'result' => [
                        'success' => false,
                        'form'    => $this->renderView('edu/partials/bulk-transaction-form.html.twig',
                            ['form' => $form->createView()])
                    ]
                ]);
            }
        }

        // Render the view
        return $this->render('edu/create-bulk.html.twig', ['form' => $form->createView()]);
    }
}