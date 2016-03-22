<?php

namespace AppBundle\Model;

use AppBundle\Entity\User;
use AppBundle\Entity\UserIdentity;
use Bindeo\DataModel\Exceptions;
use Bindeo\Util\ApiConnection;
use Symfony\Component\Translation\TranslatorInterface;

class UserModel
{
    private $api;
    private $translator;

    /**
     * UserModel constructor.
     *
     * @param ApiConnection           $api
     * @param TranslatorInterface $translator
     */
    public function __construct(ApiConnection $api, TranslatorInterface $translator)
    {
        $this->api = $api;
        $this->translator = $translator;
    }

    /**
     * Change the necessary user data
     *
     * @param User $user
     * @param User $newUser
     *
     * @return array
     */
    public function changeIdentity(User $user, User $newUser)
    {
        $newUser->clean();
        $newUser->setEmail(mb_strtolower($newUser->getEmail()));

        // Flag to mark when the user changed
        $changed = false;

        // Change identity only if it was changed
        if ($newUser->getOldEmail() != $newUser->getEmail() or $user->getName() != $newUser->getName()) {
            // If email has changed, password is necessary
            if ($newUser->getOldEmail() != $newUser->getEmail() and !password_verify($newUser->getPassword(),
                    $user->getPassword())
            ) {
                return ['error' => ['password', $this->translator->trans('Your password is not correct')]];
            }

            // Change the identity
            $res = $this->api->putJson('account_identities', (new UserIdentity())->setIdUser($newUser->getIdUser())
                                                                                 ->setName($newUser->getName())
                                                                                 ->setValue($newUser->getEmail())
                                                                                 ->setIp($newUser->getIp())
                                                                                 ->toArray());

            // Check api errors
            if ($res->getError()) {
                if ($res->getError()['message'] == Exceptions::DUPLICATED_KEY) {
                    return ['error' => ['email', $this->translator->trans('The email is already used')]];
                } else {
                    return [
                        'error' => [
                            '',
                            $this->translator->trans('There was a problem with your request, please try it later')
                        ]
                    ];
                }
            } else {
                // Update logged user if he changed
                if ($res->getRows()[0]->getName() != $user->getName() or $res->getRows()[0]->getEmail() != $user->getEmail()) {
                    $user->setName($res->getRows()[0]->getName())->setEmail($res->getRows()[0]->getEmail());
                    $changed = true;
                }
            }
        } elseif (!$user->getConfirmed()) {
            // Resend the validation token
            $this->api->getJson('account_token', $newUser->toArray());
        } else {
            $changed = true;
        }

        // Return data
        return ['success' => true, 'changed' => $changed];
    }
}