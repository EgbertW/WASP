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

class Site
{
    private $vhosts = array();
    private $locales = array();

    public function __construct(Dictionary $config = null)
    {
        $this->setConfig($config);
    }

    public function setConfig(Dictionary $config)
    {
        $hosts = ($config->has('domain', Dictionary::TYPE_ARRAY)) ? $config->get('domain') : array();
    }

    public function addVirtualHost(VirtualHost $host)
    {
        $host->setSite($this);
        $this->vhosts[] = $host;
        foreach ($host->getLocales() as $locale)
            $this->locales[$locale] = true;
    }

    public function getVirtualHosts()
    {
        return $this->vhosts;
    }

    public function getLocales()
    {
        return array_keys($this->locales);
    }

    public function match($url)
    {
        $url = new URL($url);
        foreach ($this->vhosts as $vhost)
        {
            if ($vhost->match($url))
                return $vhost;
        }
        return null;
    }

    public function checkRedirect($url)
    {
        $url = new URL($url);
        foreach ($this->vhosts as $vhost)
        {
            if (!$vhost->match($url))
                continue;

            $redirect = $vhost->getRedirect();
            if ($redirect)
            {
                $path = $vhost->getPath($url);     
                return $redirect->URL($path);
            }

            $host = $vhost->getHost();
            if ($host->scheme !== $url->scheme)
            {
                $url->scheme = $host->scheme;
                return $url;
            }
        }
        return false;
    }

    public function URL($path, $locale = null)
    {
        if (empty($locale))
            $locale = getlocale(LC_MESSAGES, 0);

        $locale = Locale::canonicalize($locale);
        foreach ($this->vhosts as $vhost)
        {
            if ($vhost->matchLocale($locale))
                return $vhost->URL($path);
        }
    }
}
