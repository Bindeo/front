<?php

namespace AppBundle\Entity;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Populate an API request
 * @package AppBundle\Entity
 */
class ResultSet
{
    private $entity;
    private $rows;
    private $numRows;
    private $curPage;
    private $numPages;
    private $error;

    /**
     * Get the appropriate object
     *
     * @param \stdClass $data
     *
     * @return array
     */
    private function getObject(\stdClass $data)
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
            case 'user_identity':
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
            case 'file_type':
                // Fill the entity class
                if (!$this->entity) {
                    $this->entity = 'AppBundle\Entity\FileType';
                } elseif ($this->entity != 'AppBundle\Entity\FileType') {
                    throw new HttpException(500);
                }

                $object = new FileType((array)$data->attributes);
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
            default:
                throw new HttpException(500);
        }

        return $res;
    }

    public function __construct(\stdClass $data = null)
    {
        // Convert the answer into objects
        if ($data === null) {
            $this->numRows = 0;
            $this->numPages = 0;
        } elseif (isset($data->data)) {
            $this->rows = [];
            if (isset($data->total_pages)) {
                // List of data
                $this->numPages = $data->total_pages;
                $this->curPage = isset($data->current_page) ? $data->current_page : 1;
                $this->numRows = count($data->data);

                foreach ($data->data as $row) {
                    list($key, $row) = $this->getObject($row);
                    $this->rows[$key] = $row;
                }
            } else {
                // Single registry
                list($key, $row) = $this->getObject($data->data);
                $this->rows[0] = $row;
                $this->numRows = 1;
            }
        } elseif (isset($data->error)) {
            $this->error = (array)$data->error;
        }
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @return array
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    /**
     * @return int
     */
    public function getCurPage()
    {
        return $this->curPage;
    }

    /**
     * @return mixed
     */
    public function getNumPages()
    {
        return $this->numPages;
    }

    /**
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }
}