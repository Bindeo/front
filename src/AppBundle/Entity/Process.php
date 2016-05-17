<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\ProcessAbstract;

class Process extends ProcessAbstract
{
    /**
     * Represents information about my status in the process
     * @var array
     */
    private $user;
    /**
     * @var array
     */
    private $others;
    /**
     * @var int
     */
    private $total;
    /**
     * @var int
     */
    private $pending;

    // Extra methods
    /**
     * @param string $userEmail Email that represents user
     */
    public function calculateData($userEmail)
    {
        // Only for signature process
        if ($this->type == 'S' and ($data = $this->getAdditionalData(true))) {
            // Calculate if logged user is signer
            $this->others = $data;
            if (isset($data[$userEmail])) {
                $this->user = ['signer' => 1, 'signed' => (int)$data[$userEmail]['signed']];
                unset($this->others[$userEmail]);
            } else {
                $this->user = ['signer' => 0, 'signed' => 0];
            }

            // Calculate pending signers
            $this->total = count($data);
            $this->pending = 0;
            foreach ($this->others as $signer) {
                if (!$signer['signed']) {
                    $this->pending++;
                }
            }
        }
    }

    /**
     * To get information for library action
     * @return array
     */
    public function getInfoUser()
    {
        return $this->user;
    }

    /**
     * To get information for library action
     * @return array
     */
    public function getInfoOther()
    {
        return reset($this->others);
    }

    /**
     * To get information for library action
     * @return int
     */
    public function getInfoTotal()
    {
        return $this->total;
    }

    /**
     * To get information for library action
     * @return int
     */
    public function getInfoPending()
    {
        return $this->pending;
    }
}