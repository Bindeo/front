<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use Bindeo\Util\ApiConnection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class UserProvider implements UserProviderInterface
{
    private $api;

    public function __construct(ApiConnection $api)
    {
        $this->api = $api;
    }

    /**
     * Loads the user for the given username.
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        // Get the user via API
        $res = $this->api->getJson('users', ['email' => $username]);
        if ($res->getError() or !isset($res->getRows()[0]) or !($user = $res->getRows()[0])) {
            throw new UsernameNotFoundException();
        } else {
            $user->setIdentities($this->api->getJson('account_identities', ['idUser' => $user->getIdUser()])
                                           ->getRows());

            return $user;
        }
    }

    /**
     * Refreshes the user for the account interface.
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     *
     * @return UserInterface
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        } else {
            $current = $user->getCurrentIdentity();

            // Check current cache stored identity and compare with current user identity
            if ($current) {
                // check if current identity is the true current identity
                $identity = $user->cacheFetchIdentity();

                // If current identity is not valid, we unset it
                if (!$identity or $identity->getEmail() != $current->getEmail()) {
                    $current = null;
                }
            }

            if (!$current) {
                // Get identities
                $user->setIdentities($this->api->getJson('account_identities', ['idUser' => $user->getIdUser()])
                                               ->getRows());

                // Store in apc
                $user->cacheStoreIdentity();
            }
        }

        // Check user and identity integrity
        if ($user->getCurrentIdentity() and ($user->getEmail() != $user->getCurrentIdentity()->getEmail() and
                                                   $user->getEmail() != $user->getCurrentIdentity()->getOldValue())
        ) {
            // Check if current user and identity are synchronized, if not, we logout user
            throw new UsernameNotFoundException();
        }

        return $user;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === 'AppBundle\Entity\User';
    }
}