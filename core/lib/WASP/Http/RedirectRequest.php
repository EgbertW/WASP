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

use Exception;
use WASP\TerminateRequest;

/**
 * Provides a way to generate interceptable and testable redirects
 */
final class RedirectRequest extends \Exception
{
    private $url = $url;
    private $timeout;
    private $request = null;

    /**
     * Create a new Redirect request.
     * @param URL $url The URL where to redirect to
     * @param int $status_code A suggestion for the HTTP status code. By
     *                         default 307: temporary redirect.
     * @param int $timeout The amount of seconds to wait before performing the redirect
     */
    public function __construct(URL $url, int $status_code = 302, int $timeout = 0)
    {
        // 3XX are only valid redirect status codes
        if ($status_code < 300 || $status_code > 399)
            throw new \RuntimeException("A redirect should have a 3XX status code, not: " . $status_code);

        parent::__construct("Redirect request to: " . $url, $status_code);
        $this->timeout = $timeout;
    }

    /**
     * Set the request ot use. If this function is not called,
     * execute() will use the default / current request.
     *
     * Mainly useful for testing.
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Return the URL to where the redirect points
     * @return URL The URL
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * Return the status code suggested for the redirect
     * @return int The status code
     */
    public function getStatusCode()
    {
        return $this->getCode();
    }

    /**
     * Perform the redirect by setting the header
     */
    public function execute($terminate = true)
    {
        $req = $this->request === null ? Request::current() : $this->request;
        $req->setHttpResponseCode($this->status_code);
        if ($this->timeout)
            $req->setHeader('Refresh', $timeout . '; url=' . $this->url);
        else
            $req->setHeader('Location', $this->url);

        if ($terminate)
            throw new TerminateRequest('Terminating after redirect');
    }
}
