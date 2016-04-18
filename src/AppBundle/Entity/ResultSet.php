<?php

namespace AppBundle\Entity;

use Bindeo\DataModel\ClientResultSetAbstract;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Populate an API request
 * @package AppBundle\Entity
 */
class ResultSet extends ClientResultSetAbstract
{
    /**
     * Get the appropriate object
     *
     * @param \stdClass $data
     *
     * @return array
     */
    protected function getObject(\stdClass $data)
    {
        switch ($data->type) {
            case 'users':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\User';
                } elseif ($this->entity != 'AppBundle\Entity\User') {
                    throw new HttpException(500);
                }

                $object = new User((array)$data->attributes);
                $res = [$object->getIdUser(), $object];
                break;
            case 'user_identities':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\UserIdentity';
                } elseif ($this->entity != 'AppBundle\Entity\UserIdentity') {
                    throw new HttpException(500);
                }

                $object = new UserIdentity((array)$data->attributes);
                $res = [$object->getIdIdentity(), $object];
                break;
            case 'files':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\File';
                } elseif ($this->entity != 'AppBundle\Entity\File') {
                    throw new HttpException(500);
                }

                $object = new File((array)$data->attributes);
                $res = [$object->getIdFile(), $object];
                break;
            case 'blockchain':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\BlockChain';
                } elseif ($this->entity != 'AppBundle\Entity\BlockChain') {
                    throw new HttpException(500);
                }

                $object = new BlockChain((array)$data->attributes);
                $res = [$object->getTransaction(), $object];
                break;
            case 'account_type':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\AccountType';
                } elseif ($this->entity != 'AppBundle\Entity\AccountType') {
                    throw new HttpException(500);
                }

                $object = new AccountType((array)$data->attributes);
                $res = [$object->getIdType(), $object];
                break;
            case 'media_type':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\MediaType';
                } elseif ($this->entity != 'AppBundle\Entity\MediaType') {
                    throw new HttpException(500);
                }

                $object = new MediaType((array)$data->attributes);
                $res = [$object->getIdType(), $object];
                break;
            case 'bulk_transaction':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\BulkTransaction';
                } elseif ($this->entity != 'AppBundle\Entity\BulkTransaction') {
                    throw new HttpException(500);
                }

                $object = new BulkTransaction((array)$data->attributes);
                $res = [$object->getIdBulkTransaction(), $object];
                break;
            case 'bulk_files':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\BulkFile';
                } elseif ($this->entity != 'AppBundle\Entity\BulkFile') {
                    throw new HttpException(500);
                }

                $object = new BulkFile((array)$data->attributes);
                $res = [$object->getIdBulkFile(), $object];
                break;
            default:
                throw new HttpException(500);
        }

        return $res;
    }
}