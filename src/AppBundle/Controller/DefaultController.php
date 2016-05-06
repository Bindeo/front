<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request)
    {
        // If we are already logged we redirect the user to the logged homepage
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            // If the user is confirmed we can access to private areas
            if ($this->getUser()->getConfirmed()) {
                return new RedirectResponse($this->generateUrl('file_library'));
            } else {
                // If we are not in / url, we redirect to it
                if($request->server->get('REDIRECT_URL') != '/') {
                    return new RedirectResponse('/');
                }

                // If user is not confirmed we render a closed index
                return $this->render('default/no-confirmed.html.twig', ['user' => $this->getUser()]);
            }
        }

        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..'),
        ]);
    }

    /**
     * Change language for not logged users
     * @Route("/ajax/public/change-locale", name="ajax_change_locale")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function ajaxChangeLocaleAction(Request $request)
    {
        if (($locale = $request->get('l')) and in_array($locale, ['es_ES', 'en_US'])) {
            $request->getSession()->set('_locale', $locale);
        }

        return new JsonResponse(['result' => ['success' => true]]);
    }
}
