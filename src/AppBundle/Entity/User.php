<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserAbstract;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

class User extends UserAbstract implements UserInterface
{
    const ROLE_UNCONFIRMED = 0;
    const ROLE_ADMIN       = 1;
    const ROLE_USER        = 2;
    const ROLE_VIP         = 3;

    private $roles = [0 => 'ROLE_UNCONFIRMED', 1 => 'ROLE_ADMIN', 2 => 'ROLE_USER', 3 => 'ROLE_VIP'];
    /**
     * Auxiliar attribute
     */
    private $totalStorage;

    // Set mandatory fields for forms
    /**
     * @Assert\NotBlank(groups={"registration", "login", "password-reset", "unconfirmed-email"})
     * @Assert\Length(max=128, groups={"registration", "login", "password-reset", "unconfirmed-email"})
     * @Assert\Email(
     *     groups={"registration", "unconfirmed-email"},
     *     strict = true,
     *     checkMX = true
     * )
     * @Assert\Email(
     *     groups={"login", "password-reset"},
     *     strict = true
     * )
     */
    protected $email;

    /**
     * @Assert\NotBlank(groups={"registration"})
     * @Assert\Length(min=2, max=256, groups={"registration"})
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"registration", "login", "change-email", "change-password"})
     * @Assert\Length(min=6, max=4096, groups={"registration", "login", "change-email", "change-password"})
     */
    protected $password;

    /**
     * @Assert\NotBlank(groups={"change-password-private"})
     * @Assert\Length(min=6, max=4096, groups={"change-password-private"})
     */
    protected $oldPassword;

    /**
     * @var UserIdentity[]
     */
    protected $identities;

    /**
     * @return UserIdentity[]
     */
    public function getIdentities()
    {
        return $this->identities;
    }

    /**
     * @param UserIdentity[] $identities
     *
     * @return $this
     */
    public function setIdentities($identities)
    {
        $this->identities = $identities;

        return $this;
    }

    /**
     * @return UserIdentity
     */
    public function getCurrentIdentity()
    {
        if ($this->identities) {
            return reset($this->identities);
        } else {
            return null;
        }
    }

    // ENTITY METHODS

    // SECURITY METHODS
    /**
     * Returns the roles granted to the user.
     * @return (Role|string)[] The user roles
     */
    public function getRoles()
    {
        if (!$this->confirmed) {
            $roles = [$this->roles[0]];
        } elseif (isset($this->roles[$this->type])) {
            $roles = [$this->roles[$this->type]];
        } else {
            $roles = null;
        }

        return $roles;
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

    /**
     * @return mixed
     */
    public function getTotalStorage()
    {
        return $this->totalStorage;
    }

    /**
     * @param mixed $totalStorage
     *
     * @return $this
     */
    public function setTotalStorage($totalStorage)
    {
        $this->totalStorage = $totalStorage;

        return $this;
    }

    /**
     * Store current identity in cache
     * @return $this
     */
    public function cacheStoreIdentity()
    {
        // Store in apc
        apc_store('use_identity_' . $this->idUser, $this->getCurrentIdentity());

        return $this;
    }

    /**
     * Fetch current identity from cache
     * @return mixed
     */
    public function cacheFetchIdentity()
    {
        return apc_fetch('use_identity_' . $this->idUser);
    }
}