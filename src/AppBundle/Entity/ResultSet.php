<?php

namespace AppBundle\Entity;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Populate an API request
 * @package AppBundle\Entity
 */
class ResultSet
{
    private $_entity;
    private $_rows;
    private $_numRows;
    private $_curPage;
    private $_numPages;
    private $_error;

    /**
     * Get the object
     *
     * @param \stdClass $data
     *
     * @return array
     */
    private function _getObject(\stdClass $data)
    {
        switch ($data->type) {
            case 'users':
                // Fill the entity class
                if (!$this->_entity) {
                    $this->_entity = 'AppBundle\Entity\User';
                } elseif ($this->_entity != 'AppBundle\Entity\User') {
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
            $this->_rows = [];
            if (isset($data->data->total_pages)) {
                // List of data
                $this->_numPages = $data->data->total_pages;
                $this->_curPage = isset($data->data->total_pages) ? $data->data->total_pages : 1;
                $this->_numRows = count($data->data);

                foreach ($data->data as $row) {
                    list($key, $row) = $this->_getObject($row);
                    $this->_rows[$key] = $row;
                }
            } else {
                // Single registry
                list($key, $row) = $this->_getObject($data->data);
                $this->_rows[0] = $row;
                $this->_numRows = 1;
            }
        } elseif (isset($data->error)) {
            $this->_error = (array)$data->error;
        }
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->_entity;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->_rows;
    }

    /**
     * @return array
     */
    public function getNumRows()
    {
        return $this->_numRows;
    }

    /**
     * @return int
     */
    public function getCurPage()
    {
        return $this->_curPage;
    }

    /**
     * @return mixed
     */
    public function getNumPages()
    {
        return $this->_numPages;
    }

    /**
     * @return array
     */
    public function getError()
    {
        return $this->_error;
    }
}