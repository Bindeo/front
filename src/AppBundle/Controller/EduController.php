<?php

namespace AppBundle\Controller;

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
                $bulkTransaction->setIdUser($this->getUser()->getIdUser())->setIp($request->getClientIp());
                // Send to the api
                $res = $this->get('app.api_connection')->postJson('bulk_transaction', $bulkTransaction->toArray());

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
                    return new JsonResponse([
                        'result' => [
                            'success'   => true,
                            'html'      => $this->renderView('data/partials/file-upload-ok.html.twig', ['message' => 'bulk'])
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