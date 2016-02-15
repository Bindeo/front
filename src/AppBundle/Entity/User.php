<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserAbstract;
use Symfony\Component\Security\Core\User\UserInterface;

class User extends UserAbstract implements UserInterface
{
    private $_roles = [1 => 'ROLE_ADMIN', 2 => 'ROLE_VIP', 3 => 'ROLE_USER'];

    /**
     * Returns the roles granted to the user.
     * <code>
     * public function getRoles()
     * {
     *     return array('ROLE_USER');
     * }
     * </code>
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     * @return (Role|string)[] The user roles
     */
    public function getRoles()
    {
        if (isset($this->_roles[$this->_type])) {
            return [$this->_roles[$this->_type]];
        } else {
            return null;
        }
    }

    /**
     * Returns the salt that was originally used to encode the password.
     * This can return null if the password was not encoded using a salt.
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     * @return string The username
     */
    public function getUsername()
    {
        return $this->_email;
    }

    /**
     * Removes sensitive data from the user.
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
        $this->_oldPassword = null;
    }
}