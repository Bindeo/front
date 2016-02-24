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

    /**
     * Check if the api response has results
     *
     * @param $res
     *
     * @return ResultSet
     */
    private function processResult($res)
    {
        if ($this->curl->httpStatusCode == 204) {
            return new ResultSet();
        } else {
            return new ResultSet($res);
        }
    }

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
        return $this->processResult($this->curl->get($this->baseUrl . $this->getRoute($url), $params));
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
        return $this->processResult($this->curl->post($this->baseUrl . $this->getRoute($url), $params));
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
        return $this->processResult($this->curl->put($this->baseUrl . $this->getRoute($url), $params));
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
        return $this->processResult($this->curl->patch($this->baseUrl . $this->getRoute($url), $params));
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
        return $this->processResult($this->curl->delete($this->baseUrl . $this->getRoute($url), $params));
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