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
    private $curl;
    private $baseUrl;
    private $routes;

    public function __construct($baseUrl, $token, $routes)
    {
        $this->baseUrl = $baseUrl;
        $this->curl = new Curl();
        $this->curl->setHeader('Authorization', 'Bearer ' . $token);
        $this->routes = $routes;
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
        return new ResultSet($this->curl->get($this->baseUrl . $url, $params));
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
        return new ResultSet($this->curl->post($this->baseUrl . $url, $params));
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
        return new ResultSet($this->curl->put($this->baseUrl . $url, $params));
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
        return new ResultSet($this->curl->patch($this->baseUrl . $url, $params));
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
        return new ResultSet($this->curl->delete($this->baseUrl . $url, array(), $params));
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
        return isset($this->routes[$route]) ? $this->routes[$route] : '';
    }
}