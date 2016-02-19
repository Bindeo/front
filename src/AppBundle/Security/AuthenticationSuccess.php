<?php

namespace AppBundle\Security;

use AppBundle\Model\ApiConnection;
use \Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccess implements AuthenticationSuccessHandlerInterface
{
    private $api;
    private $monolog;

    public function __construct(ApiConnection $api, LoggerInterface $monolog)
    {
        $this->api = $api;
        $this->monolog = $monolog;
    }

    /**
     * This is called when an interactive authentication attempt succeeds. This
     * is called by authentication listeners inheriting from AbstractAuthenticationListener.
     *
     * @param Request        $request
     * @param TokenInterface $token
     *
     * @return Response never null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        // The user has logged into the system, we tell it to the API
        $res = $this->api->getJson($this->api->getRoute('account'), [
            'email'    => $token->getUsername(),
            'password' => $request->get('_password'),
            'ip'       => $request->getClientIp()
        ]);
        if ($res->getError() or !($user = $res->getRows()[0])) {
            // There is a problem login in the api, we need to logout the user
            $this->monolog->critical('CRITICAL ERROR: Login into API', [
                'email'    => $token->getUsername(),
                'password' => $request->get('_password'),
                'ip'       => $request->getClientIp()
            ]);

            return new RedirectResponse('/logout');
        }

        // if the user hit a secure page and start() was called, this was
        // the URL they were on, and probably where you want to redirect to
        $targetPath = $request->getSession()->get('_security.' . $token->getProviderKey() . '.target_path');

        $response = new RedirectResponse($targetPath ? $targetPath : '/');

        // Save the user language in a cookie
        $response->headers->setCookie(new Cookie('LOCALE', $token->getUser()->getLang(), 31536000 + time()));

        return $response;
    }
}