<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\BulkTransactionAbstract;
use Symfony\Component\Validator\Constraints as Assert;

class BulkTransaction extends BulkTransactionAbstract
{
    public function __construct($array = [])
    {
        parent::__construct($array);

        $this->files = [];
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

    /**
     * Returns an array with the object attributes
     * @return array
     */
    public function toArray()
    {
        // Get base array
        $array = parent::toArray();

        // Generate files array
        $files = [];
        if ($this->files) {
            foreach ($this->files as $file) {
                $files[] = $file->toArray();
            }
        }
        $array['files'] = json_encode($files);

        return $array;
    }
}