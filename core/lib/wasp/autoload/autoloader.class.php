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
namespace WASP\Autoload;

use WASP\Debug;

require_once "resolve.class.php";

class Autoloader
{
    /**
     * The spl_autoloader that loads classes from the core and installed modules
     */
    public static function autoload($class_name)
    {
        $path = Resolve::class($class_name);

        if ($path === null)
            return false;

        require_once $path;
        if (class_exists($class_name))
            Debug\info("WASP.Autoload.Autoloader", "Loaded class {} from path {}", $class_name, $path);
        else
            Debug\error("WASP.Autoload.Autoloader", "File {} does not contain class {}", $path, $class_name);
    }
}

// Set up the autoloader
spl_autoload_register(array('WASP\\Autoload\\Autoloader', 'autoload'));
