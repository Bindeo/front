<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Entity\UserIdentity;
use AppBundle\Form\Type\EditPreferencesType;
use AppBundle\Form\Type\ChangePasswordType;
use AppBundle\Form\Type\ChangeIdentityType;
use AppBundle\Form\Type\RegisterType;
use AppBundle\Form\Type\PasswordResetType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            $user->setType(User::ROLE_USER)->setIp($request->getClientIp())->setLang($request->getLocale());
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
                $user->setIdentities($api->getJson('account_identities', ['idUser' => $user->getIdUser()])->getRows());
                $token = new UsernamePasswordToken($user, null, "main", $user->getRoles());
                $this->get("security.token_storage")->setToken($token);

                $session = $this->get('session');
                $session->set('_security_' . "main", serialize($token));
                $session->save();

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
     * @Route("/user/configuration", name="user_configuration")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function configurationAction(Request $request)
    {
        // Get the logged user
        $user = $this->getUser()->setIp($request->getClientIp());
        $response = new Response();

        // Preferences form
        list($formPreferences, $successPreferences) = $this->preferences($request, $user, $response);
        list($formPassword, $successPassword) = $this->password($request, $user);

        return $this->render('user/configuration.html.twig', [
            'formPreferences'    => $formPreferences->createView(),
            'successPreferences' => $successPreferences,
            'formPassword'       => $formPassword->createView(),
            'successPassword'    => $successPassword
        ], $response);
    }

    /**
     * Manage preferences form
     *
     * @param Request  $request
     * @param User     $user
     * @param Response $response
     *
     * @return array
     */
    private function preferences(Request $request, User $user, Response $response)
    {
        $successPreferences = false;
        $formPreferences = $this->createForm(EditPreferencesType::class, $user);
        $formPreferences->handleRequest($request);

        // Form submitted
        if ($formPreferences->isSubmitted() && $formPreferences->isValid()) {
            // Save the changes against the api
            $result = $this->get('app.api_connection')->putJson('account', $user->toArray());

            // Check for errors
            if ($result->getError() or !(is_subclass_of($result->getRows()[0], '\Bindeo\DataModel\UserAbstract'))) {
                $error = $this->get('translator')
                              ->trans('We have a problem updating your preferences, please try again later');

                $formPreferences->addError(new FormError($error));

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
                $successPreferences = true;
            }
        }

        return [$formPreferences, $successPreferences];
    }

    /**
     * Manage change password form
     *
     * @param Request $request
     * @param User    $user
     *
     * @return array
     */
    private function password(Request $request, User $user)
    {
        $successPassword = false;
        $newUser = new User(['idUser' => $user->getIdUser(), 'ip' => $user->getIp()]);
        $formPassword = $this->createForm(ChangePasswordType::class, $newUser);
        $formPassword->handleRequest($request);

        // Form submitted
        if ($formPassword->isSubmitted() && $formPassword->isValid()) {
            // Save the changes against the api
            $result = $this->get('app.api_connection')->putJson('account_password', $newUser->toArray());

            // Check for errors
            if ($result->getError() or !(is_subclass_of($result->getRows()[0], '\Bindeo\DataModel\UserAbstract'))) {
                if ($result->getError()['code'] == 403) {
                    $error = $this->get('translator')->trans('Your password is not correct');

                    $formPassword->get('oldPassword')->addError(new FormError($error));
                } else {
                    $error = $this->get('translator')
                                  ->trans('We have a problem updating your password, please try again later');

                    $formPassword->addError(new FormError($error));

                    // There is a problem login in the api, we need to logout the user
                    $this->get('logger')->error('ERROR: Edit password', [
                        'data'  => $newUser->toArray(),
                        'error' => $result->getError()['message']
                    ]);
                }
            } else {
                // Change the password in the session
                $user->setPassword($result->getRows()[0]->getPassword());

                // Success
                $successPassword = true;
            }
        }

        return [$formPassword, $successPassword];
    }

    /**
     * @Route("/ajax/private/close-account", name="ajax_close_account")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ajaxCloseAccountAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // Verify user password
        if (!password_verify($request->get('password'), $user->getPassword())) {
            return new JsonResponse(['result' => ['success' => false]]);
        }

        // Close the account
        $res = $this->get('app.api_connection')
                    ->deleteJson('account', $user->setIp($request->getClientIp())->toArray());

        if ($res->getError()) {
            return new JsonResponse(['result' => ['success' => false]]);
        } else {
            return new JsonResponse(['result' => ['success' => true, 'url' => $this->generateUrl('logout')]]);
        }
    }

    /**
     * @Route("/user/identity", name="user_identity")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function identityAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // Get user main identity
        $identity = clone $user->getCurrentIdentity();

        $identity->setOldValue($identity->getValue())->setOldDocument($identity->getDocument());
        $form = $this->createForm(ChangeIdentityType::class, $identity);
        $form->handleRequest($request);

        // Form submitted
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Data is valid, modify the user and send the confirm email
                $res = $this->get('app.model.user')->changeIdentity($user, $identity->setIp($request->getClientIp()));

                if (isset($res['error'])) {
                    if ($res['error'][0] == '') {
                        $form->addError(new FormError($res['error'][1]));
                    } else {
                        $form->get($res['error'][0] == 'email' ? 'value' : $res['error'][0])
                             ->addError(new FormError($res['error'][1]));
                    }
                }

                // Check if the form is still valid
                if ($form->isValid()) {
                    return new JsonResponse([
                        'result' => [
                            'success' => true,
                            'form'    => $this->renderView('user/partials/identities-form.html.twig',
                                ['form' => $form->createView(), 'success' => true, 'changed' => $res['changed']])
                        ]
                    ]);
                } else {
                    return new JsonResponse([
                        'result' => [
                            'success' => false,
                            'form'    => $this->renderView('user/partials/identities-form.html.twig',
                                ['form' => $form->createView(), 'success' => false])
                        ]
                    ]);
                }
            } else {

                return new JsonResponse([
                    'result' => [
                        'success' => false,
                        'form'    => $this->renderView('user/partials/identities-form.html.twig',
                            ['form' => $form->createView(), 'success' => false])
                    ]
                ]);
            }
        }

        return $this->render('user/identities.html.twig', ['form' => $form->createView(), 'success' => false]);
    }

    /**
     * @Route("/ajax/unconfirmed/change-email", name="ajax_change_email")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ajaxChangeEmailAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException();
        }

        if (!($email = $request->get('e'))) {
            $error = 'notvalid';
        } else {
            // Validate email
            $validator = $this->get('validator');
            $res = $validator->validate(new User(['email' => $email]), null, ['unconfirmed-email']);

            if (!$res->count()) {
                $error = '';
                // Logged user
                /** @var User $user */
                $user = $this->getUser();

                // Get user main identity and modify it
                $identity = clone $user->getCurrentIdentity();

                // If user does not have identity, we need to get it

                $identity->setOldValue($identity->getValue())
                         ->setOldDocument($identity->getDocument())
                         ->setValue($email);

                // Data is valid, modify the user and send confirmation email
                $res = $this->get('app.model.user')
                            ->changeIdentity($user,
                                $identity->setIp($request->getClientIp())->setPassword($user->getPassword()));

                if (isset($res['error'])) {
                    $error = 'repeated';
                } else {
                    // Correct change, we need to update user if he changed his email
                    if ($identity->getOldValue() != $identity->getValue()) {
                        // Refresh user
                        $token = $this->get('security.token_storage')->getToken();
                        $token->getUser()->setEmail($identity->getValue())->cacheStoreIdentity();
                        $token->setAuthenticated(false);
                    }
                }
            } else {
                $error = 'notvalid';
            }
        }

        return new JsonResponse([
            'result' => [
                'success' => !$error,
                'error'   => $error
            ]
        ]);
    }

    /**
     * @Route("/user/validate", name="validate_token")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function validateTokenAction(Request $request)
    {
        if (!($token = $request->get('t')) or !($type = $request->get('m'))) {
            throw new NotFoundHttpException();
        }

        $api = $this->get('app.api_connection');

        // If we are changing password, we ask for the new one
        if ($type == 'P') {
            if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                return new RedirectResponse('/');
            }
            $user = new User();
            $form = $this->createForm(ChangePasswordType::class, $user);
            $form->handleRequest($request);

            // Form submitted
            if ($form->isSubmitted() && $form->isValid()) {
                // Reset password
                // Try to validate the token against the API
                $result = $api->putJson('account_token', [
                    'token'    => $token,
                    'ip'       => $request->getClientIp(),
                    'password' => $user->getPassword()
                ]);

                if ($result->getError()) {
                    $success = false;
                } else {
                    $success = true;
                }
            } else {
                return $this->render('user/password-reset-second.html.twig', ['form' => $form->createView()]);
            }
        } else {
            // Try to validate the token against the API
            $result = $api->putJson('account_token', ['token' => $token, 'ip' => $request->getClientIp()]);

            if ($result->getError()) {
                $success = false;
            } else {
                $success = true;
                // If the user is logged, update the entity
                if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                    // Update user data and refresh roles
                    $token = $this->get('security.token_storage')->getToken();
                    $token->getUser()
                          ->setConfirmed(1)
                          ->setEmail($result->getRows()[0]->getEmail())
                          ->setName($result->getRows()[0]->getName())
                          ->setIdentities(null);
                    $token->setAuthenticated(false);
                }

                // Clean cache
                $result->getRows()[0]->cacheStoreIdentity();
            }
        }

        // Render confirmation
        return $this->render('user/token.html.twig', ['success' => $success, 'type' => $type]);
    }

    /**
     * @Route("/user/password-reset", name="password_reset")
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function passwordResetAction(Request $request)
    {
        // If we are already logged we redirect the user to the homepage
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return new RedirectResponse('/');
        }

        $user = new User();
        $form = $this->createForm(PasswordResetType::class, $user);
        $form->handleRequest($request);

        // Form submitted
        if ($form->isSubmitted() && $form->isValid()) {
            // Reset password
            $this->get('app.api_connection')
                 ->getJson('account_password', $user->setIp($request->getClientIp())->toArray());

            return $this->render('user/password-reset-ok.html.twig',
                ['email' => $user->getEmail(), 'contact' => $this->getParameter('emails')['contact']]);
        }

        return $this->render('user/password-reset.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Check if user has been confirmed in other navigator or machine
     * @Route("/ajax/unconfirmed/check-confirmed", name="ajax_check_confirmed")
     *
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ajaxCheckConfirmedAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // Get user
        $res = $this->get('app.api_connection')->getJson('users', ['idUser' => $user->getIdUser()]);

        if ($res->getError()) {
            return new JsonResponse(['result' => ['success' => false]]);
        } else {
            $res = $res->getRows();
            $userDb = reset($res);

            // Check if user is confirmed
            if ($userDb->getConfirmed()) {
                // Refresh roles
                $token = $this->get('security.token_storage')->getToken();
                $token->getUser()->setConfirmed(1);
                $token->setAuthenticated(false);

                return new JsonResponse(['result' => ['success' => true]]);
            } else {
                return new JsonResponse(['result' => ['success' => false]]);
            }
        }
    }

    /**
     * Check if user has been confirmed in other navigator or machine
     * @Route("/ajax/unconfirmed/resend-token", name="ajax_resend_token")
     *
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ajaxResendTokenAction(Request $request)
    {
        // Logged user
        /** @var User $user */
        $user = $this->getUser();

        // Resend the validation token
        $res = $this->get('app.api_connection')->getJson('account_token', $user->toArray());

        if ($res->getError()) {
            return new JsonResponse(['result' => ['success' => false]]);
        } else {
            return new JsonResponse(['result' => ['success' => true]]);
        }
    }
}