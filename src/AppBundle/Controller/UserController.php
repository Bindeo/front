<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Form\Type\EditProfileType;
use AppBundle\Form\Type\RegisterType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends Controller
{
    /**
     * @Route("/login", name="login")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function loginAction(Request $request)
    {
        // If we are already logged we redirect the user to the homepage
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return new RedirectResponse('/');
        }

        $authenticationUtils = $this->get('security.authentication_utils');

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('user/login.html.twig', [
            // last username entered by the user
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    /**
     * @Route("/register", name="register")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function registerAction(Request $request)
    {
        // If we are already logged we redirect the user to the homepage
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return new RedirectResponse('/');
        }

        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);
        $form->handleRequest($request);

        // Form submitted
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setType(User::ROLE_USER)->setIp($request->getClientIp())->setLang($request->getSession()
                                                                                             ->get('_locale'));
            // We can register the user against the API
            $api = $this->get('app.api_connection');
            $result = $api->postJson('account', $user->toArray());

            // Check errors in the registry process
            if ($result->getError() or !isset($result->getRows()[0])) {
                if ($result->getError()['code'] == 409) {
                    $error = $this->get('translator')->trans('The email is already used');
                } else {
                    $error = $this->get('translator')
                                  ->trans('We have a problem creating your account, please try again later');

                    // There is a problem login in the api, we need to logout the user
                    $this->get('logger')->critical('CRITICAL ERROR: Register into API', [
                        'data'  => $user->toArray(),
                        'error' => $result->getError()['message']
                    ]);
                }

                $form->addError(new FormError($error));
            } else {
                // User registered so we need to login him
                $res = $api->getJson('account', [
                    'email'    => $user->getEmail(),
                    'password' => $user->getPassword(),
                    'ip'       => $user->getIp()
                ]);

                if ($res->getError() or !($user = $res->getRows()[0])) {
                    // There is a problem login in the api, we need to logout the user
                    $this->get('logger')->critical('CRITICAL ERROR: Login into API', [
                        'email'    => $user->getEmail(),
                        'password' => $user->getPassword(),
                        'ip'       => $user->getIp()
                    ]);

                    return new RedirectResponse('/logout');
                }

                // Create the user token
                $token = new UsernamePasswordToken($user, '', "public", $user->getRoles());
                $this->get("security.token_storage")->setToken($token);

                // Fire the login event
                // Logging the user in above the way we do it doesn't do this automatically
                $event = new InteractiveLoginEvent($request, $token);
                $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

                return new RedirectResponse('/');
            }
        }

        return $this->render('user/register.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/user/profile", name="edit_profile")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editProfileAction(Request $request)
    {
        // Get the logged user
        $user = $this->getUser();
        $form = $this->createForm(EditProfileType::class, $user);
        $form->handleRequest($request);
        $response = new Response();
        $success = false;

        // Form submitted
        if ($form->isSubmitted() && $form->isValid()) {
            // Save the changes against the api
            $result = $this->get('app.api_connection')
                           ->putJson('account', $user->setIp($request->getClientIp())->toArray());

            /** @var User $user */
            if ($result->getError() or !(is_subclass_of($result->getRows()[0], '\Bindeo\DataModel\UserAbstract'))) {
                $error = $this->get('translator')
                              ->trans('We have a problem creating your account, please try again later');

                $form->addError(new FormError($error));

                // There is a problem login in the api, we need to logout the user
                $this->get('logger')->error('ERROR: Edit profile', [
                    'data'  => $user->toArray(),
                    'error' => $result->getError()['message']
                ]);
            } else {
                // If the language has changed, we need to transmit changes to session, cookie and translator
                if ($request->getSession()->get('_locale') != $user->getLang()) {
                    $request->getSession()->set('_locale', $user->getLang());
                    $response->headers->setCookie(new Cookie('LOCALE', $user->getLang(), 31536000 + time()));
                    $this->get('translator')->setLocale($user->getLang());
                }

                // Success
                $success = true;
            }
        }

        return $this->render('user/edit-profile.html.twig', ['form' => $form->createView(), 'success' => $success],
            $response);
    }

    /**
     * @Route("/user/validate", name="validate_token")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function validateTokenAction(Request $request)
    {
        if (!($token = $request->get('t'))) {
            throw new NotFoundHttpException();
        }

        // Try to validate the token against the API
        $result = $this->get('app.api_connection')
                       ->putJson('account_token', ['token' => $token, 'ip' => $request->getClientIp()]);

        if ($result->getError()) {
            $success = false;
        } else {
            $success = true;
            // If the user is logged, update the entity
            if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                $this->getUser()->setConfirmed(1);
            }
        }

        return $this->render('user/token.html.twig', ['success' => $success]);
    }
}