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
     * Get the object
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
            default:
                throw new HttpException(500);
        }

        return $res;
    }

    public function __construct(\stdClass $data)
    {
        // Convert the answer into objects
        if (isset($data->data)) {
            $this->rows = [];
            if (isset($data->data->total_pages)) {
                // List of data
                $this->numPages = $data->data->total_pages;
                $this->curPage = isset($data->data->total_pages) ? $data->data->total_pages : 1;
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