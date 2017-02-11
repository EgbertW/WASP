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

namespace WASP;

class VirtualHost
{
    private $url;
    private $redirect;
    private $locale;

    public function __construct($hostname, $locale)
    {
        $this->url = new URL($hostname);
        $this->setLocale($locale);
    }

    public function matchLocale($locale)
    {
        return $redirect === null && $this->locale === $locale;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setLocale(string $locale)
    {
        $this->locale = \Locale::canonicalize($locale);
        return $this;
    }

    public function setRedirect($hostname)
    {
        if (!empty($hostname))
        {
            $this->redirect = new URL($hostname); 
            $this->redirect->set('path', rtrim($this->redirect->path, '/');
        }
        else
            $this->redirect = false;
        return $this;
    }

    public function URL($path = '')
    {
        $url = new URL($this->url);
        $path = ltrim($path, '/');
        $url->set('path', $url->path . '/' . $path);
        return $url;
    }

    public function getHost()
    {
        return $this->url;
    }

    public function getPath($url)
    {
        $url = new URL($url);
        $path = str_replace($this->url->path, '', $url->path);

        return $path; 
    }

    public function isSecure()
    {
        return $this->url->getScheme() === "https";
    }

    public function hasWWW()
    {
        return strtolower(substr($this->url->path, 0, 4)) === "www.";
    }

    public function match($url)
    {
        try
        {
            $url = new URL($url);
        }
        catch (URLException $e)
        {
            return false;
        }

        return $url->getHost() === $this->url->getHost();
    }

    public function getRedirect()
    {
        return $this->redirect;
    }
}

