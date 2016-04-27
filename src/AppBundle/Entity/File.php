<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\FileAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class File extends FileAbstract
{
    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $mode;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $path;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $fileOrigName;

    /**
     * @Assert\Valid()
     */
    protected $signers;

    /**
     * @var User
     */
    protected $user;

    protected $signType;

    public function __construct($array = [])
    {
        parent::__construct($array);

        $this->signers = [];
    }

    /**
     * @param Signer $signer
     */
    public function addSigner(Signer $signer)
    {
        $this->signers[] = $signer;
    }

    /**
     * @param Signer $signer
     */
    public function removeSigner(Signer $signer)
    {
        for ($i = 0, $count = count($this->signers); $i < $count; $i++) {
            if ($this->signers[$i]->getEmail() == $signer->getEmail()) {
                unset($this->signers[$i]);
            }
        }
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     *
     * @return File
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSignType()
    {
        return $this->signType;
    }

    /**
     * @param mixed $signType
     *
     * @return $this
     */
    public function setSignType($signType)
    {
        $this->signType = $signType;

        return $this;
    }

    /**
     * Returns an array with the object attributes
     * @return array
     */
    public function toArray()
    {
        // Get base array
        $array = parent::toArray();

        // Generate signers array
        $signers = [];
        if ($this->signers) {
            foreach ($this->signers as $signer) {
                $signers[] = $signer->toArray();
            }
        }
        $array['signers'] = json_encode($signers);

        return $array;
    }
}