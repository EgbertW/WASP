<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP\Http;

use DateTime;
use DateInterval;

class Cookie
{
    private $name;
    private $value;
    private $expiry;
    private $domain;
    private $secure;
    private $httponly;
    private $path;

    public function __construct(string $name, string $value, $request = null)
    {
        $this->name = $name;
        $this->value = $value;
        $this->httponly = true;
    }

    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setValue(string $value)
    {
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setHttpOnly(bool $httponly)
    {
        $this->httponly = $httponly;
        return $this;
    }

    public function getHttpOnly()
    {
        return $this->httponly;
    }

    public function setDomain(string $domain)
    {
        $this->domain = $domain;
        return $this;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setURL(URL $url)
    {
        $this->domain = $url->getHost();
        $this->path = $url->getPath();
        return $this;
    }

    public function getURL()
    {
        return new URL($this->domain . '/' . $this->path);
    }

    public function setPath(string $path)
    {
        $this->path = $path;
        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setExpiresIn(DateInterval $interval)
    {

    }

    public function setExpires(DateTime $date)
    {

    }

    public function setExpiresNow()
    {
        $this->expiry = 0;
        return $this;
    }

    public function getExpires()
    {
        return $this->expiry;
    }
    
}
