<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\UserIdentityAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class UserIdentity extends UserIdentityAbstract
{
    /**
     * @Assert\NotBlank(groups={"identity-email"})
     * @Assert\Email(
     *     groups={"identity-email"},
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
}