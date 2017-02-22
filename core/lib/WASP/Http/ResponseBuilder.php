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

use WASP\AssetManager;
use WASP\Debug\Logger;
use WASP\Debug\DevLogger;
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

    /** The asset manager manages injection of CSS and JS script inclusion */
    private $asset_manager = null;

    /**
     * Create the response to a Request
     * @param Request $request The request this is the response to
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        // Check for a Dev-logger
        $rootlogger = Logger::getLogger();
        $handlers = $rootlogger->getLogHandlers();
        foreach ($handlers as $h)
            if ($h instanceof DevLogger)
                $this->addHook($h);

        $this->asset_manager = new AssetManager($request);
        $this->asset_manager->setMinified(!$request->config->dget('site', 'dev', false));
        $this->asset_manager->setTidy($request->config->dget('site', 'tidy-output', false));
        $this->addHook($this->asset_manager);
    }

    /**
     * Set the response object
     *
     * @param Response $response The final response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        $response->setRequest($this->request);
        return $this;
    }

    public function getAssetManager()
    {
        return $this->asset_manager;
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
    public function setHeader(string $name, string $value)
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
        $ob_cnt = 0;
        while (ob_get_level())
        {
            ++$ob_cnt;
            $contents = ob_get_contents();
            ob_end_clean();
        
            $lines = explode("\n", $contents);
            foreach ($lines as $n => $line)
            {
                if (!empty($line))
                    self::$logger->debug("Script output: {0}/{1}: {2}", [$ob_cnt, $n + 1, $line]);
            }
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

        // Add Content-Type mime header
        $mime = $this->response->getMimeTypes();
        if (empty($mime))
            $mime = Request::cli() ? "text/plain" : "text/html";
        elseif (is_array($mime))
            $mime = $this->request->getBestResponseType($mime);

        $this->setHeader('Content-Type', $mime);
            
        try
        {
            $transformed = $this->response->transformResponse($mime);
            if ($transformed instanceof Response)
                $this->response = $tranformed;
        }
        catch (Throwable $e)
        {} // Proceed unmodified

        // Execute hooks
        foreach ($this->hooks as $hook)
        {
            try
            {
                $hook->executeHook($this->request, $this->response, $mime);
            }
            catch (\Throwable $e)
            {
                self::$logger->alert('Error while running hooks: {0}', [$e]);
                $this->response = new Error(500, "Error while running hooks", $e);
            }
        }

        // Set HTTP response code
        $code = $this->response->getStatusCode();
        http_response_code($code);

        // Add headers from response to the final response
        foreach ($this->response->getHeaders() as $key => $value)
            $this->setHeader($key, $value);

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
        $this->response->output($mime);

        // We're done
        self::$logger->debug("** Finished processing request to {0}", [$this->request->url]);
        die();
    }
}

// @codeCoverageIgnoreStart
ResponseBuilder::setLogger();
// @codeCoverageIgnoreEnd
