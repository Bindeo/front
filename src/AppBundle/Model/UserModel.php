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
     * @param ApiConnection       $api
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
     * @param User         $user
     * @param UserIdentity $identity
     *
     * @return array
     */
    public function changeIdentity(User $user, UserIdentity $identity)
    {
        $identity->clean();

        // Flag to mark when the user changed
        $changed = false;

        // Change identity only if it was changed
        if ($identity->getOldValue() != $identity->getValue() or $user->getName() != $identity->getName() or
            $identity->getDocument() != $identity->getOldDocument()
        ) {
            // If email has changed, password is necessary
            if ($identity->getOldValue() != $identity->getValue() and
                ($user->getConfirmed() and !password_verify($identity->getPassword(), $user->getPassword()))
            ) {
                $identity->setDocument($identity->getOldDocument())->setName($user->getName());

                return ['error' => ['password', $this->translator->trans('Your password is not correct')]];
            }

            // Change the identity
            $res = $this->api->putJson('account_identities', $identity->toArray());

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
                if ($res->getRows()[0]->getName() != $user->getName() or
                    $res->getRows()[0]->getEmail() != $user->getEmail()
                ) {
                    $user->setName($res->getRows()[0]->getName())->setEmail($res->getRows()[0]->getEmail());
                    $changed = true;
                }
            }
        } elseif (!$user->getConfirmed()) {
            // Resend the validation token
            $this->api->getJson('account_token', $user->toArray());
        } else {
            $changed = true;
        }

        // Return data
        if ($changed) {
            $user->setIdentities([$identity->getIdIdentity() => $identity]);
        }

        return ['success' => true, 'changed' => $changed];
    }
}