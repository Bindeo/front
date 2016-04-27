<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\SignerAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class Signer extends SignerAbstract
{
    /**
     * @Assert\NotBlank(groups={"upload-file"})
     * @Assert\Length(min=2, max=256, groups={"upload-file"})
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     * @Assert\Length(max=128, groups={"upload-file"})
     * @Assert\Email(
     *     groups={"upload-file"},
     *     strict = true,
     *     checkMX = true
     * )
     */
    protected $email;

    /**
     * @Assert\Length(max=12, groups={"upload-file"})
     */
    protected $phone;
}