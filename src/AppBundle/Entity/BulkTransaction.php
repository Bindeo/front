<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\BulkTransactionAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class BulkTransaction extends BulkTransactionAbstract
{
    protected $files;

    public function __construct($array = [])
    {
        parent::__construct($array);

        $this->files = [];
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param array $files
     *
     * @return $this
     */
    public function setFiles($files)
    {
        $this->files = $files;

        return $this;
    }

    /**
     * @param BulkFile $file
     */
    public function addFile(BulkFile $file)
    {
        $this->files[$file->getUniqueId()] = $file;
    }

    public function removeFile(BulkFile $file)
    {
        unset($this->files[$file->getUniqueId()]);
    }
}