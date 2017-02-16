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

use WASP\DB\DB;
use WASP\Debug\LoggerAwareStaticTrait;
use WASP\Http\Request;
use WASP\Http\Response;
use WASP\Http\Error as HttpError;
use Throwable;

/**
 * Run / include an application and make sure it produces response.
 * All output if buffered and will be logged to the logger at the end, to
 * avoid sending garbage to the client.
 */
class AppRunner
{
    use LoggerAwareStaticTrait;

    /** The request being handled */
    private $request;

    /** The application to execute */
    private $app;

    /**
     * Create the AppRunner with the request and the app path
     * @param WASP\Http\Request $request The request being answered
     * @param string $app The path to the appplication to run
     */
    public function __construct(Request $request, string $app)
    {
        $this->app = $app;
        $this->request = $request;
    }

    /**
     * Run the app and make produce a response.
     * @throws WASP\Http\Response
     */
    public function execute()
    {
        try
        {
            // No output should be produced by apps directly, so buffer
            // everything.  All buffers will be closed by the ResponseHandler,
            // so no need to do that here.
            ob_start();
            $response = $this->doExecute();
            if ($response instanceof Response)
                throw $response;
            throw new HttpError(500, "App did not produce any output");
        }
        catch (HttpError $response)
        {
            self::$logger->info("While executing controller: {0}", [$this->app]);
            self::$logger->notice(
                "Failed request ended in {0} - URL: {1}", 
                [$response->getCode(), $this->request->url]
            );
            throw $response;
        }
        catch (Response $response)
        {
            self::$logger->info("While executing controller: {0}", [$this->app]);
            self::$logger->info(
                "Request handled succesfully, status code {0} - URL: {1}",
                [$response->getCode(), $this->request->url]
            );
            throw $response;
        }
        catch (Throwable $e)
        {
            self::$logger->info("While executing controller: {0}", [$this->app]);
            self::$logger->notice(
                "Unexpected exception of type {0} thrown while processing request to URL: {1}", 
                [get_class($e), $this->request->url]
            );
            throw $e;
        }
    }

    /**
     * A wrapper to execute / include the selected route. This puts the app in a
     * private scope, with access to the most commonly used variables:
     * $request The request object
      
     * $db A database connection
     * $url The URL that was requested
     * $get The GET parameters sent to the script
     * $post The POST parameters sent to the script
     * $url_args The URL arguments sent to the script (remained of the URL after the selected route)
     *
     * @param string $path The file to execute
     * @throws WASP\Http\Error When the route did not end and also did to execute a Template.
     */
    private function doExecute()
    {
        // Prepare some variables that come in handy in apps
        $request = $this->request;
        $config = $request->config;
        $url = $request->url;
        $db = DB::get();
        $get = $request->get;
        $post = $request->post;
        $url_args = $request->url_args;
        $path = $this->app;

        self::$logger->debug("Including {0}", [$path]);
        include $path;

        if (Template::$last_template === null)
            throw new HttpError(400, $request->url);
    }
}

// @codeCoverageIgnoreStart
AppRunner::setLogger();
// @codeCoverageIgnoreEnd
