<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\FileAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class File extends FileAbstract
{
    /**
     * @Assert\NotBlank(groups={"upload-file"})
     * @Assert\Length(max=128)
     */
    protected $name;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $idType;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $path;

    /**
     * @Assert\NotBlank(groups={"upload-file"})
     */
    protected $fileOrigName;

    /**
     * @var User
     */
    protected $user;

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
}