<?php

namespace Mcprohosting\Spacegdn;

use GuzzleHttp\Client;

class Bridge implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Guzzle instance.
     *
     * @var Client
     */
    protected $guzzle;

    /**
     * URL endpoint for the GDN.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Route parts to request.
     *
     * @var array
     */
    protected $route;

    /**
     * Results of the query.
     *
     * @var \stdClass
     */
    protected $results;

    /**
     * List of GDN operators.
     *
     * @var array
     */
    public static $operators = array(
        '='  => 'eq',
        '<'  => 'lt',
        '>'  => 'gt',
        '<=' => 'lteq',
        '>=' => 'gteq'
    );

    /**
     * List of columns on GDN models.
     *
     * @var array
     */
    public static $columns = array(
        'jar'     => array('id', 'name', 'site_url', 'created_at', 'updated_at'),
        'channel' => array('id', 'jar_id', 'name', 'created_at', 'updated_at'),
        'version' => array('id', 'channel_id', 'version', 'created_at', 'updated_at'),
        'build'   => array('id', 'version_id', 'build', 'size', 'checksum', 'url', 'created_at', 'updated_at'),
    );

    /**
     * List of all request parameters to be converted into results.
     *
     * @var array
     */
    protected $parameters;

    public function __construct(Client $guzzle = null)
    {
        $this->guzzle = $guzzle ?: new Client;
        $this->clear();
    }

    /**
     * Sets the GDN URL to use.
     *
     * @param string $endpoint
     * @return self
     */
    public function setEndpoint($endpoint)
    {
        if (strpos($endpoint, '//') === false) {
            $endpoint = 'http://' . $endpoint;
        }

        $this->endpoint = rtrim($endpoint, '/') . '/';

        return $this;
    }

    /**
     * Resets parameters.
     *
     * @return array
     */
    public function clear()
    {
        $this->parameters = array();
        $this->route = array('v1');
        $this->results = null;

        return $this;
    }

    /**
     * Sets the jar ID to filter by.
     *
     * @param string $id
     * @return self
     */
    public function jar($id)
    {
        array_push($this->route, 'jar', $id);

        return $this;
    }

    /**
     * Sets the version ID to filter by.
     *
     * @param string $id
     * @return self
     */
    public function version($id)
    {
        array_push($this->route, 'version', $id);

        return $this;
    }

    /**
     * Sets the channel ID to filter by.
     *
     * @param string $id
     * @return self
     */
    public function channel($id)
    {
        array_push($this->route, 'channel', $id);

        return $this;
    }

    /**
     * Sets the build ID to filter by.
     *
     * @param string $id
     * @return self
     */
    public function build($id)
    {
        array_push($this->route, 'build', $id);

        return $this;
    }

    /**
     * Sets the resource to get.
     *
     * @param string $item
     * @return self
     */
    public function get($item = '')
    {
        if ($item) {
            $this->route[] = rtrim($item, 's');
        }

        return $this;
    }

    /**
     * Adds a "where" query to the request.
     *
     * @param string $column
     * @param string $operator
     * @param string|array $value
     * @return self
     */
    public function where($column, $operator, $value)
    {
        $column = $this->resolveColumn($column);

        if ($operator === 'in') {
            $p = array($column, 'in', implode('.', $value));
        } else {
            $p = array($column, self::$operators[$operator], $value);
        }

        $this->parameters['where'] = implode('.', $p);

        return $this;
    }

    /**
     * Sets the page to get.
     *
     * @param integer $page
     * @return self
     */
    public function page($page)
    {
        $this->parameters['page'] = $page;

        return $this;
    }

    /**
     * Sets the order for the request.
     *
     * @param string $column
     * @param string $direction One of "asc", "desc"
     * @return self
     */
    public function orderBy($column, $direction)
    {
        $this->parameters['sort'] = $this->resolveColumn($column) . '.' . $direction;

        return $this;
    }

    /**
     * Takes a columns string and identified it with the appropriate model.
     *
     * @param string $column
     * @return string|bool
     */
    protected function resolveColumn($column)
    {
        if (strpos($column, '.')) {
            return $column;
        }

        foreach ($this::$columns as $model => $columns) {
            if (in_array($column, $columns)) {
                return $model . '.' . $column;
            }
        }

        return false;
    }

    /**
     * Gets the results of the query, executing the query if it's not yet been done.
     *
     * @return \stdClass
     */
    public function results()
    {
        if (!$this->results) {

            $response = $this->guzzle->get($this->buildUrl());
            
            $this->results = $response->json();
        }

        return $this->results;
    }

    /**
     * Builds a request URL
     *
     * @return string
     */
    protected function buildUrl()
    {
        $base = $this->endpoint . implode('/', $this->route) . '?';

        foreach ($this->parameters as $key => $value) {
            $base .= urlencode($key) . '=' . urlencode($value) . '&';
        }

        return $base . 'json';
    }

    public function offsetSet($offset, $value)
    {
        $this_array = $this->toArray();
        if (is_null($offset)) {
            $this_array[] = $value;
        } else {
            $this_array[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->toArray()[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->toArray()[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->toArray()[$offset]) ? $this->toArray()[$offset] : null;
    }

    public function count()
    {
        return count($this->toArray());
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * Returns the results as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->results()['results'];
    }

    /**
     * Returns the results as a JSON string.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray());
    }

    /**
     * Gets a property of the results.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        return $this->results()[$attribute];
    }
} 
