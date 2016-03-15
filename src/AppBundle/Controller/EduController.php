<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BulkFile;
use AppBundle\Entity\BulkTransaction;
use AppBundle\Form\Type\BulkTransactionType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Entity\User;

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

        $form = $this->createForm(BulkTransactionType::class, $bulkTransaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() and $form->isValid()) {
            dump($bulkTransaction);
        }

        $view = $form->createView();

        return $this->render('edu/create-bulk.html.twig', ['form' => $view]);
    }
}