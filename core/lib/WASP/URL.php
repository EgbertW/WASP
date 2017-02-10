<?php

namespace WASP;

class URLException extends \RuntimeException
{}

/**
 * URL is a class that parses and modifies URLs. This
 * class is limited to a limited set of schemes - it
 * supports http, https and ftp.
 */
class URL implements \ArrayAccess
{
    private $scheme = null;
    private $port = null;
    private $username = null;
    private $password = null;
    private $host = null;
    private $path = null;
    private $query = null;
    private $fragment = null;

    public function __construct($url = "", $default_scheme = '')
    {
        if (empty($url))
            return;

        if ($url instanceof URL)
            $parts = $url;
        else
            $parts = self::parse($url, $default_scheme);

        $this->scheme = $parts['scheme'];
        $this->port = $parts['port'];
        $this->username = $parts['username'];
        $this->password = $parts['password'];
        $this->setHost($parts['host']);
        $this->path = $parts['path'];
        $this->query = $parts['query'];
        $this->fragment = $parts['fragment'];
    }

    public static function parse(string $url, string $default_scheme = '')
    {
        if (!preg_match('/^(((([a-z]+):)?\/\/)?((([^:]+):([^@]+)@)?([\w\d.-]+))(:([1-9][0-9]*))?)?(\/.*)?$/u', $url, $matches))
            throw new URLException("Invalid URL: " . $url);

        $scheme = !empty($matches[4]) ? $matches[4] : null;
        $user   = !empty($matches[7]) ? $matches[7] : null;
        $pass   = !empty($matches[8]) ? $matches[8] : null;
        $host   = !empty($matches[9]) ? $matches[9] : null;
        $port   = !empty($matches[11]) ? (int)$matches[11] : null;
        $path   = !empty($matches[12]) ? $matches[12] : '/';

        if (empty($scheme))
            $scheme = $default_scheme;

        if (!in_array($scheme, array('http', 'https', 'ftp')))
            throw new URLException("Unsupported scheme: '" . $scheme . "'");

        $query = null;
        $fragment = null;
        if (preg_match('/^(.*?)(\\?([^#]*))?(#(.*))?$/u', $path, $matches))
        {
            $path = $matches[1];
            $query = !empty($matches[3]) ? $matches[3] : null;
            $fragment = !empty($matches[5]) ? $matches[5] : null;
        }

        return array(
            'scheme' => $scheme,
            'username' => $user,
            'password' => $pass,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query,
            'fragment' => $fragment
        );
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function toString($idn = false)
    {
        $o = "";
        if (!empty($this->scheme))
            $o .= $this->scheme . "://";
        
        if (!empty($this->username) && !empty($this->password))
            $o .= $this->username . ':' . $this->password . '@';

        // Check for IDN
        $host = $this->host;
        if ($idn && preg_match('/[^\x20-\x7f]/', $host))
            $host = \idn_to_ascii($host);
        $o .= $host;

        if (!empty($this->port))
        {
            if (!(
                ($this->scheme === "http"  && $this->port === 80) ||
                ($this->scheme === "https" && $this->port === 443) || 
                ($this->scheme === "ftp"   && $this->port === 21)
            ))
                $o .= ":" . $this->port;
        }

        $o .= $this->path;
        if (!empty($this->query))
            $o .= '?' . $this->query;
        if (!empty($this->fragment))
            $o .= '#' . $this->fragment;
        return $o;
    }

    public function setHost($hostname)
    {
        if (substr($hostname, 0, 4) === "xn--")
            $hostname = \idn_to_utf8($hostname);
        $this->host = $hostname;
        return $this;
    }

    // ArrayAccess implementation
    public function offsetGet($offset)
    {
        if (property_exists($this, $offset))
            return $this->$offset;
        throw new \OutOfRangeException($offset);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === 'host')
            return $this->setHost($value);
        if (!property_exists($this, $offset))
            throw new \OutOfRangeException($offset);
        $this->$offset = $value;
    }

    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    public function offsetUnset($offset)
    {
        if (property_exists($this, $offset))
            $this->$offset = null;
    }

    public function __get($field)
    {
        if (property_exists($this, $field))
            return $this->$field;
        throw new \OutOfRangeException($field);
    }

    public function __set($field, $value)
    {
        if ($field === 'host')
            return $this->setHost($value);
        if (!property_exists($this, $field))
            throw new \OutOfRangeException($field);
        $this->$field = $value;
    }
}
