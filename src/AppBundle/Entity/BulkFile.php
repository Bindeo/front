<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\BulkFileAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class BulkFile extends BulkFileAbstract
{
    /**
     * @Assert\NotBlank(groups={"create-bulk"})
     */
    protected $uniqueId;

    /**
     * @Assert\NotNull(groups={"create-bulk"})
     */
    protected $fileType;

    /**
     * @Assert\NotNull(groups={"create-bulk"})
     */
    protected $idSign;

    /**
     * @Assert\NotBlank(groups={"create-bulk"})
     * @Assert\Length(max=128)
     */
    protected $fullName;

    /**
     * @Assert\NotBlank(groups={"create-bulk"})
     * @Assert\Date()
     */
    protected $fileDate;

    /**
     * @Assert\NotBlank(groups={"create-bulk"})
     */
    protected $path;

    /**
     * @Assert\NotBlank(groups={"create-bulk"})
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