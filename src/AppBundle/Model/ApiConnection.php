<?php

namespace AppBundle\Model;

use AppBundle\Entity\ResultSet;
use \Curl\Curl;

/**
 * Manage the connection with the api
 * @package AppBundle\Model
 */
class ApiConnection
{
    private $_curl;
    private $_baseUrl;
    private $_routes;

    public function __construct($baseUrl, $token, $routes)
    {
        $this->_baseUrl = $baseUrl;
        $this->_curl = new Curl();
        $this->_curl->setHeader('Authorization', 'Bearer ' . $token);
        $this->_routes = $routes;
    }

    /**
     * GET request against the API
     *
     * @param string $url
     * @param array  $params [optional]
     *
     * @return ResultSet
     */
    public function getJson($url, $params = [])
    {
        return new ResultSet($this->_curl->get($this->_baseUrl . $url, $params));
    }

    /**
     * POST request against the API
     *
     * @param string $url
     * @param array  $params [optional]
     *
     * @return ResultSet
     */
    public function postJson($url, $params = [])
    {
        return new ResultSet($this->_curl->post($this->_baseUrl . $url, $params));
    }

    /**
     * POST request against the API
     *
     * @param string $url
     * @param array  $params [optional]
     *
     * @return ResultSet
     */
    public function putJson($url, $params = [])
    {
        return new ResultSet($this->_curl->put($this->_baseUrl . $url, $params));
    }

    /**
     * POST request against the API
     *
     * @param string $url
     * @param array  $params [optional]
     *
     * @return ResultSet
     */
    public function patchJson($url, $params = [])
    {
        return new ResultSet($this->_curl->patch($this->_baseUrl . $url, $params));
    }

    /**
     * POST request against the API
     *
     * @param string $url
     * @param array  $params [optional]
     *
     * @return ResultSet
     */
    public function deleteJson($url, $params = [])
    {
        return new ResultSet($this->_curl->delete($this->_baseUrl . $url, array(), $params));
    }

    /**
     * Return an API route
     *
     * @param $route
     *
     * @return string
     */
    public function getRoute($route)
    {
        return isset($this->_routes[$route]) ? $this->_routes[$route] : '';
    }
}