<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\DocsSignatureAbstract;
use Bindeo\DataModel\UserInterface;

class DocsSignature extends DocsSignatureAbstract
{
    /**
     * Convert all compound data from Json
     * @return $this
     */
    public function convertObjects()
    {
        // Bulk transaction
        if ($this->bulk and !($this->bulk instanceof BulkTransaction)) {
            $this->bulk = new BulkTransaction((array)$this->bulk);
        }

        // Blockchain
        if ($this->blockchain and !($this->blockchain instanceof BlockChain)) {
            $this->blockchain = new BlockChain((array)$this->blockchain);
        }

        // Files
        if ($this->files and is_array($this->files)) {
            // Populate each file
            $files = [];
            foreach ($this->files as $file) {
                $files[] = new File((array)$file);
            }
            $this->files = $files;
        }

        // Owner
        if ($this->issuer and $this->issuerType and !($this->issuer instanceof UserInterface)) {
            // Populate object depends on the type
            if ($this->issuerType == 'U') {
                $this->issuer = new UserIdentity((array)$this->issuer);
            } elseif ($this->issuerType == 'C') {
                $this->issuer = new OAuthClient((array)$this->issuer);
            }
        }

        // Signers
        if ($this->signers and is_array($this->signers)) {
            // Populate each file
            $signers = [];
            foreach ($this->signers as $signer) {
                $signers[] = new Signer((array)$signer);
            }
            $this->signers = $signers;
        }

        return $this;
    }
}