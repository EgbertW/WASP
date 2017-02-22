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

use WASP\Http\URL;

class VirtualHost
{
    protected $url;
    protected $site = null;
    protected $redirect;
    protected $locales;

    public function __construct($hostname, $locale)
    {
        $this->url = new URL($hostname);
        $this->locales = array();
        $this->setLocale($locale);
    }

    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    public function getSite()
    {
        return $this->site;
    }

    public function matchLocale($locale)
    {
        if (!empy($redirect))
            return false;
            
        return in_array($locale, $this->locale);
    }

    public function getLocales()
    {
        return $this->locales;
    }

    public function setLocale($locale)
    {
        $locale = cast_array($locale);
        foreach ($locale as $l)
            $this->locales[] = \Locale::canonicalize($l);

        // Remove duplicate locales
        $this->locales = array_unique($this->locales);
        return $this;
    }

    public function setRedirect($hostname)
    {
        if (!empty($hostname))
        {
            $this->redirect = new URL($hostname); 
            $this->redirect->set('path', rtrim($this->redirect->path, '/'));
        }
        else
            $this->redirect = false;
        return $this;
    }

    public function URL($path = '')
    {
        $url = new URL($this->url);
        $path = ltrim($path, '/');
        $url->set('path', $url->path . $path);

        if ($url->host === $this->url->host && $url->scheme === $this->url->scheme && $url->port === $this->url->port)
        {
            $url->host = null;
            $url->scheme = null;
        }
        return $url;
    }

    public function getHost()
    {
        return $this->url;
    }

    public function getPath($url)
    {
        $url = new URL($url);
        $to_replace = $this->url->path;
        $path = $url->path;

        if (strpos($path, $to_replace) === 0)
            $path = substr($path, strlen($to_replace));

        $path = '/' . $path;
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

        return $url->host === $this->url->host;
    }

    public function getRedirect()
    {
        return $this->redirect;
    }
}

