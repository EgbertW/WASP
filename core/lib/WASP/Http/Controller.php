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

use WASP\Dictionary;

/**
 * The controller class can be subclassed by controllers to structure
 * calling of the controller.
  *
  * To use this, define your class as subclass of WASP\Controller and
  * add methods to this class with names corresponding to the first unmatched
  * part of the route. E.g., if your controller is /image and the called route is
  * /image/edit/3, your class should contain a method called 'edit' that accepts
  * one argument of int-type.
  *
  * The parameters of your methods are extracted using reflection and matched to the
  * request. You can use string or int types, or subclasses of WASP\DB\DAO. In the
  * latter case, the object will be instantiated using the parameter as identifier,
  * that will be passed to the DAO::fetchSingle method.
  */
abstract class Controller extends StringResponse
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function dispatch()
    {
        $urlargs = $this->request->url_args;
        $controller = $urlargs->shift();
        if (!method_exists($this, $controller))
            throw new Error(404, "Unknown controller: " . $controller);

        $method = new ReflectionMethod($this, $controller);
        $parameters = $method->getParameters();

        if (count($controller) === 0 && count($parameters) === 0)
            return $this->$controller();

        $args = array();
        foreach ($parameters as $cnt => $param)
        {
            $tp = $param->getType();
            if ($tp === null && $urlargs->has(0))
            {
                $args[] = $urlargs->shift();
                continue;
            }

            $tp = (string)$tp;

            if ($tp === "WASP\\Dictionary")
            {
                if ($cnt !== (count($parameters) - 1))
                    throw new Error(500, "Dictionary must be last parameter");

                $args[] = $urlargs;
                break;
            }
            
            if ($tp === "int")
            {
                if (!$urlargs->has(0, Dictionary::TYPE_INT))
                    throw new Error(400, "Invalid arguments");
                $args[] = (int)$urlargs->shift();
                continue;
            }

            if ($tp === "string")
            {
                if (!$urlargs->has(0, Dictionary::TYPE_STRING))
                    throw new Error(400, "Invalid arguments");
                $args[]  = (string)$urlargs->shift();
                continue;
            }

            if (class_exists($tp) && is_subclass_of($tp, "WASP\DB\DAO"))
            {
                if (!$urlargs->has(0))
                    throw new Error(400, "Invalid arguments");
                $object_id = $urlargs->shift();    
                $obj = call_user_func(array($tp, "fetchSingle"), $object_id);
                $args[] = $obj;
                continue;
            }
        }

        call_user_func_array(array($this, $controller), $args);
        if (empty($this->output))
            throw new Error(500, "No valid response returned");

        return $this;
    }
}
