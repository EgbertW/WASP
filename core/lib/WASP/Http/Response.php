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

abstract class Response extends \Exception
{
    /** 
     * A reference to the request obejct
     */
    protected $request = null;

    /**
     * A mime type of the response
     */
    protected $mime = null;

    /** 
     * Set the request associated to this response 
     * @return WASP\Http\Response Provides fluent interface
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Return the associated request. If none was set, the default is returned.
     * @return WASP\Http\Request The associated request
     */
    public function getRequest()
    {
        if ($this->request === null)
            $this->request = Request::current();
        return $this->request;
    }

    /**
     * @return int The HTTP Status code
     */
    public function getStatusCode()
    {
        return $this->getCode();
    }

    /**
     * Sets the HTTP Response code
     * @parma int $code The HTTP Response code (100-599)
     */
    public function setStatusCode(int $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Return the configured mime type
     */
    public function getMime()
    {
        return $this->mime;
    }

    /**
     * Set the mime type of the response.
     * @param string $mime The mime type, that will be sent as the content-type
     */
    public function setMime(string $mime)
    {
        $this->mime = $mime;
        return $this;
    }

    /**
     * Provide additional headers to be set on the request
     */
    public function getHeaders()
    {
        return array();
    }

    /**
     * Output the data to the client. This will be the very last method called
     * by the ResponseBuilder, and all output buffering will have been
     * disabled. Headers will have been sent, just send the output.
     */
    abstract public function output();
}
