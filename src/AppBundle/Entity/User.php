<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserAbstract;
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
     * @Assert\NotBlank(groups={"registration", "login", "pre-upload"})
     * @Assert\Email(
     *     groups={"registration", "pre-upload"},
     *     strict = true,
     *     checkMX = true
     * )
     * @Assert\Email(
     *     groups={"login"},
     *     strict = true
     * )
     */
    protected $email;

    protected $oldEmail;

    /**
     * @Assert\NotBlank(groups={"registration", "edit-profile", "pre-upload"})
     * @Assert\Length(max=256)
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"registration", "login", "change-email"})
     * @Assert\Length(min=6, max=4096, groups={"registration", "login", "change-email"})
     */
    protected $password;

    protected $identities;

    /**
     * @return mixed
     */
    public function getOldEmail()
    {
        return $this->oldEmail;
    }

    /**
     * @param mixed $oldEmail
     *
     * @return $this
     */
    public function setOldEmail($oldEmail)
    {
        $this->oldEmail = $oldEmail;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIdentities()
    {
        return $this->identities;
    }

    /**
     * @param mixed $identities
     *
     * @return $this
     */
    public function setIdentities($identities)
    {
        $this->identities = $identities;

        return $this;
    }

    // ENTITY METHODS

    // SECURITY METHODS
    /**
     * Returns the roles granted to the user.
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