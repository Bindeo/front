<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\SignerAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class Signer extends SignerAbstract
{
    /**
     * @Assert\NotBlank(groups={"upload-file", "sign-file"})
     * @Assert\Length(min=2, max=256, groups={"upload-file", "sign-file"})
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
     * @Assert\Length(max=12, groups={"upload-file", "sign-file"})
     */
    protected $phone;

    /**
     * @Assert\NotBlank(groups={"sign-file"})
     * @Assert\Length(min=4, max=4, groups={"sign-file"})
     */
    protected $code;

    protected $lang;

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     *
     * @return $this
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }
}