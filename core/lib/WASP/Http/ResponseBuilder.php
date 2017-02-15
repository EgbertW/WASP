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

use WASP\Debug\LoggerAwareStaticTrait;
use DateTime;
use DateInterval;

/**
 * Create and output a response
 */
class ResponseBuilder
{
    use LoggerAwareStaticTrait;

    /** The headers to send to the client */
    private $headers = array();

    /** The cookies to send to the client */
    private $cookies = array();

    /** The request this is a response to */
    private $request;

    /** The response to send to the client */
    private $response = null;

    /** The hooks to execute before outputting the response */
    private $hooks = array();

    /**
     * Create the response to a Request
     * @param Request $request The request this is the response to
     */
    public function __construct(Request $request)
    {
        $this->request = null;
    }

    /**
     * Set the response object
     *
     * @param Response $output The final response
     */
    public function setResponse(Response $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Add a cookie that should be sent to the client
     * @param Cookie $cookie The cookie to send
     */
    public function addCookie(Cookie $cookie)
    {
        $this->cookies[] = $cookie;
        return $this;
    }

    /**
     * @return array The cookies that should be sent to the client
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /** 
     * Set a header
     * @param string $name The name of the header
     * @param string $value The value
     */
    public function addHeader(string $name, string $value)
    {
        // Make sure the word has no spaces but dashes instead, and is
        // Camel-Cased. The dashes are first replaced with spaces to let
        // ucwords function properly, and afterwards all spaces are converted
        // to dashes.
        $name = ucwords(strtolower(str_replace('-', ' ', $name)));
        $name = str_replace(' ', '-', $name);

        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @return array all configured headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Add a hook to the ResponseHooks - these hooks will be executed just
     * before output begins. This can be used to inject or modify output.
     * @param ResponseHookInterface $hook The hook to add
     */
    public function addHook(ResponseHookInterface $hook)
    {
        $this->hooks[] = $hook;
        return $this;
    }

    /**
     * Close all active output buffers and log their contents
     */
    public function endAllOutputBuffers()
    {
        while (ob_get_level())
        {
            $contents = ob_get_contents();
            ob_end_clean();
        
            $lines = explode("\n", $contents);
            foreach ($lines as $line)
                $this->logger->debug("Script output: {0}", $line);
        }
    }
    
    /** 
     * Respont to the clients request. This will produce the output.
     * @codeCoverageIgnore This actually, finally dies, so nothing to test here.
     */
    public function respond()
    {
        // Make sure there always is a response
        if (null === $this->response)
            $this->response = new Error(500, "No output produced");
        
        // Close and log all script output that hasn't been cleaned yet
        $this->endAllOutputBuffers();

        // Execute hooks
        foreach ($this->hooks as $hook)
        {
            try
            {
                $hook->executeHook($this->request, $this->response);
            }
            catch (\Throwable $e)
            {
                $this->response = new Error(500, "Error while running hooks", $e);
            }
        }

        // Set HTTP response code
        $code = $this->response->getStatusCode();
        http_response_code($code);

        // Add Content-Type mime header
        $mime = $response->getMime();
        if (empty($mime))
            $mime = Request::cli() ? "text/plain" : "text/html";
        $this->setHeader('Content-Type', $mime);

        // Set headers
        foreach ($this->headers as $name => $value)
            header($name . ': ' . $value);
        
        // Set cookies
        foreach ($this->cookies as $cookie)
        {
            setcookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpires(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->getSecure(),
                $cookie->getHttpOnly()
            );
        }

        // Perform output
        $this->response->output();

        // We're done
        $this->logger->info("** Finished processing request to {0}", $this->request->url);
        die();
    }
}

// @codeCoverageIgnoreStart
Request::setLogger();
// @codeCoverageIgnoreEnd
