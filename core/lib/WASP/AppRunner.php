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

use ReflectionMethod;
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

    /** The initial output buffer level */
    private $output_buffer_level;

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

    private function logScriptOutput()
    {
        $output_buffers = array();
        $ob_cnt = 0;
        while (ob_get_level() > $this->output_buffer_level)
        {
            $output = trim(ob_get_contents());
            ++$ob_cnt;
            ob_end_clean();
            if (!empty($output))
            {
                $lines = explode("\n", $output);
                foreach ($lines as $n => $line)
                    self::$logger->debug("Script output: {0}/{1}: {2}", [$ob_cnt, $n + 1, $line]);
            }
        }
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
            // everything, and log afterwards
            $this->output_buffer_level = ob_get_level();

            ob_start();
            $response = $this->doExecute();

            if (is_object($response) && !($response instanceof Response))
                $response = $this->reflect($response);

            if ($response instanceof Response)
                throw $response;

            throw new HttpError(500, "App did not produce any response");
        }
        catch (HttpError $response)
        {
            self::$logger->debug("While executing controller: {0}", [$this->app]);
            self::$logger->notice(
                "Failed request ended in {0} - URL: {1}", 
                [$response->getCode(), $this->request->url]
            );
            throw $response;
        }
        catch (Response $response)
        {
            self::$logger->debug("While executing controller: {0}", [$this->app]);
            self::$logger->info(
                "Request handled succesfully, status code {0} - URL: {1}",
                [$response->getCode(), $this->request->url]
            );
            throw $response;
        }
        catch (Throwable $e)
        {
            self::$logger->debug("While executing controller: {0}", [$this->app]);
            self::$logger->notice(
                "Unexpected exception of type {0} thrown while processing request to URL: {1}", 
                [get_class($e), $this->request->url]
            );
            throw $e;
        }
        finally
        {
            $this->logScriptOutput();
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
     * @return mixed The response produced by the script, if any
     */
    private function doExecute()
    {
        // Prepare some variables that come in handy in apps
        $request = $this->request;
        $resolver = $this->request->getResolver();
        $tpl = $template = $this->request->getTemplate();
        $config = $request->config;
        $url = $request->url;
        $db = DB::get();
        $get = $request->get;
        $post = $request->post;
        $url_args = $request->url_args;
        $path = $this->app;

        self::$logger->debug("Including {0}", [$path]);
        $resp = include $path;

        return $resp;
    }
    
    /**
     * If you prefer to encapsulate your controllers in classes, you can 
     * have your app files return an object instead of a response.
     * 
     * Create a class and add methods to this class with names corresponding to
     * the first unmatched part of the route. E.g., if your controller is
     * /image and the called route is /image/edit/3, your class should contain
     * a method called 'edit' that accepts one argument of int-type.
     *
     * The parameters of your methods are extracted using reflection and
     * matched to the request. You can use string or int types, or subclasses
     * of WASP\DB\DAO. In the latter case, the object will be instantiated
     * using the parameter as identifier, that will be passed to the
     * DAO::get method.
     */
    protected function reflect($object)
    {
        $urlargs = $this->request->url_args;
        $controller = $urlargs->shift();
        if (!method_exists($object, $controller))
            throw new HttpError(404, "Unknown controller: " . $controller);

        // Inject some properties when they're public
        $vars = array_keys(get_object_vars($object));

        if (in_array('template', $vars))
            $object->template = $this->request->getTemplate();

        if (in_array('request', $vars))
            $object->request = $this->request;

        if (in_array('resolve', $vars))
            $object->resolve = $this->request->getResolver();

        if (in_array('url_args', $vars))
            $object->url_args = $this->request->url_args;

        $method = new ReflectionMethod($object, $controller);
        $parameters = $method->getParameters();

        if (count($parameters) === 0)
            return call_user_func(array($object, $controller));

        $args = array();
        $arg_cnt = 0;
        foreach ($parameters as $cnt => $param)
        {
            $tp = $param->getType();
            if ($tp === null)
            {
                ++$arg_cnt;
                if (!$urlargs->has(0))
                    throw new HttpError(400, "Invalid arguments - expecting argument $arg_cnt");

                $args[] = $urlargs->shift();
                continue;
            }

            $tp = (string)$tp;
            if ($tp === "WASP\\Dictionary")
            {
                if ($cnt !== (count($parameters) - 1))
                    throw new HttpError(500, "Dictionary must be last parameter");

                $args[] = $urlargs;
                break;
            }

            if ($tp === "WASP\\Http\\Request")
            {
                $args[] = $this->request;
                continue;
            }

            if ($tp === "WASP\\Template")
            {
                $args[] = $this->request->getTemplate();
                continue;
            }
            
            ++$arg_cnt;
            if ($tp === "int")
            {
                if (!$urlargs->has(0, Dictionary::TYPE_INT))
                    throw new HttpError(400, "Invalid arguments - missing integer as argument $cnt");
                $args[] = (int)$urlargs->shift();
                continue;
            }

            if ($tp === "string")
            {
                if (!$urlargs->has(0, Dictionary::TYPE_STRING))
                    throw new HttpError(400, "Invalid arguments - missing string as argument $cnt");
                $args[]  = (string)$urlargs->shift();
                continue;
            }

            if (class_exists($tp) && is_subclass_of($tp, "WASP\DB\DAO"))
            {
                if (!$urlargs->has(0))
                    throw new HttpError(400, "Invalid arguments - missing identifier as argument $cnt");
                $object_id = $urlargs->shift();    
                $obj = call_user_func(array($tp, "get"), $object_id);
                $args[] = $obj;
                continue;
            }

            throw new HttpError(500, "Invalid parameter type: " . $tp);
        }

        return call_user_func_array(array($object, $controller), $args);
    }
}

// @codeCoverageIgnoreStart
AppRunner::setLogger();
// @codeCoverageIgnoreEnd
