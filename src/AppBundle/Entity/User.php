<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserAbstract;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

class User extends UserAbstract implements UserInterface
{
    const ROLE_ADMIN = 1;
    const ROLE_USER  = 2;
    const ROLE_VIP   = 3;

    private $roles = [1 => 'ROLE_ADMIN', 2 => 'ROLE_USER', 3 => 'ROLE_VIP'];

    // Set mandatory fields for forms
    /**
     * @Assert\NotBlank(groups={"registration", "login"})
     * @Assert\Email(
     *     groups={"registration", "login"},
     *     strict = true,
     *     checkMX = true
     * )
     */
    protected $email;
    /**
     * @Assert\NotBlank(groups={"registration"})
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"registration", "login"})
     * @Assert\Length(min=3, max=4096, groups={"registration", "login"})
     */
    protected $password;

    /**
     * Returns the roles granted to the user.
     *
     * @return (Role|string)[] The user roles
     */
    public function getRoles()
    {
        if (isset($this->roles[$this->type])) {
            return [$this->roles[$this->type]];
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
        return $this->email;
    }

    /**
     * Removes sensitive data from the user.
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials()
    {
        $this->oldPassword = null;
    }
}