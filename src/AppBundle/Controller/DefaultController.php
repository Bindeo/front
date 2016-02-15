<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\User;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ]);
    }

    public function userAction(Request $request)
    {
        $result = $this->get('app.api_connection')->getJson('/account', ['email' => 'ivelasco@bindeo.com', 'password' => '123456', 'ip' => '37.134.64.109']);

        if(!$result->getError() and $result->getNumRows()) {
            $user = $result->getRows()[0];
            dump($user->getRoles());
        }

        return $this->render('default/hello.html.twig');
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @Route("/hello", name="hello")
     */
    public function testAction(Request $request)
    {
        return $this->render('default/hello.html.twig');
    }
}
