<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserIdentityAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class UserIdentity extends UserIdentityAbstract
{
    /**
     * @Assert\NotBlank(groups={"identity"})
     * @Assert\Email(
     *     groups={"identity"},
     *     strict = true,
     *     checkMX = true
     * )
     */
    protected $value;

    /**
     * @Assert\NotBlank(groups={"identity"})
     * @Assert\Length(max=128, groups={"identity"})
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"identity"})
     * @Assert\Length(min=4, max=12, groups={"identity"})
     */
    protected $document;

    protected $oldDocument;

    /**
     * @Assert\NotBlank(groups={"change-email"})
     * @Assert\Length(min=6, max=4096, groups={"change-email"})
     */
    protected $password;

    protected $oldValue;

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOldValue()
    {
        return $this->oldValue;
    }

    /**
     * @param mixed $oldValue
     *
     * @return $this
     */
    public function setOldValue($oldValue)
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOldDocument()
    {
        return $this->oldDocument;
    }

    /**
     * @param mixed $oldDocument
     *
     * @return $this
     */
    public function setOldDocument($oldDocument)
    {
        $this->oldDocument = $oldDocument;

        return $this;
    }
}