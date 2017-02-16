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

use Throwable;
use WASP\Http\Request;
use WASP\Http\Response;
use WASP\Http\StringResponse;
use WASP\Http\Error as HttpError;
use WASP\Debug\Logger;
use WASP\Debug\LoggerAwareStaticTrait;

class OutputHandler
{
    use LoggerAwareStaticTrait;

    private static $error_handler_set = false;

    /**
     * @codeCoverageIgnore This will not do anything from CLI except for enabling error reporting
     */
    public static function setErrorHandler()
    {
        if (self::$error_handler_set)
            return;

        // Don't repeat this function
        self::$error_handler_set = true;

        // Don't attach error handlers when running from CLI
        if (Request::cli())
        {
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 'On');
            return;
        }

        // We're processing a HTTP request, so we want to catch all errors and exceptions.
        // The applications and templates will send their output by throwing a WASP/Http/Response
        // object, so we need to catch that.
        set_exception_handler(array("WASP\\OutputHandler", "handleException"));
        set_error_handler(array("WASP\\OutputHandler", "handleError"), E_ALL | E_STRICT);
    }

    /**
     * Catch all PHP errors, notices and throw them as an exception instead.
     * @param int $errno Error number
     * @param string $errstr Error description
     * @param string $errfile The file where the error occured
     * @param int $errline The line where the error occured
     * @param mixed $errcontext Erro context
     *
     * @codeCoverageIgnore As this will stop the script, it's not good for unit testing
     */
    public static function handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        self::$logger->error("PHP Error {0}: {1} on {2}({3})", [$errno, $errstr, $errfile, $errline]);
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * @codeCoverageIgnore As this will stop the script, it's not good for unit testing
     */
    public static function handleException($exception)
    {
        // First set headers as configured in the request
        $request = Request::current();

        if (method_exists($exception, "getRequest"))
        {
            try
            {
                $r = $exception->getRequest();
                if ($r instanceof Request)
                    $request = $r;
            }
            catch (Throwable $e)
            {}
        }

        if ($request === null)
        {
            header('Content-Type: text/plain');
            echo Logger::str($exception);
            die();
        }

        if ($exception instanceof Response)
        {
            self::handleResponse($request, $exception);
        }
        elseif ($exception instanceof TerminateRequest)
        {
            self::$logger->notice("Terminate request received: {0}", $exception);
            die();
        }
        else
        {
            self::handleOtherException($request, $exception);
        }
    }

    private static function handleOtherException(Request $request, Throwable $exception)
    {
        $request = Request::current();
        self::$logger->error("Exception: {exception}", ["exception" => $exception]);
        self::$logger->error(
            "*** [{0}] Failed processing {1} request to {2}",
            [$exception->getCode(), $request->method, $request->url]
        );

        // Wrap the error in a HTTP Error 500
        $wrapped = new HttpError(500, "An error occured", $user_message = "", $exception);
        self::handleResponse($request, $wrapped);
    }

    private static function handleResponse(Request $request, Response $response)
    {
        $responseBuilder = $request->getResponseBuilder();
        $responseBuilder->setResponse($response);
        $responseBuilder->respond();
    }
}

// @codeCoverageIgnoreStart
OutputHandler::setLogger();
// @codeCoverageIgnoreEnd
