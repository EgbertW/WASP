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

class Config
{
    private static $repository = array();

    public static function getConfig($scope = 'main', $fail_safe = false)
    {
        if (!isset(self::$repository[$scope]))
        {
            if ($fail_safe)
                return null;

            $filename = Path::$CONFIG . '/' . $scope . '.ini';
            Debug\debug("WASP.Config", "Loading config from {0}", [$filename]);
            if (!file_exists($filename))
            {
                Debug\critical("WASP.Config", "Failed to load config file {0}", [$filename]);
                self::loadErrorClass();
                throw new HttpError(500, "Configuration file is missing", "Configuration could not be loaded");
            }

            self::$repository[$scope] = Dictionary::loadFile($filename);
        }

        return self::$repository[$scope];
    }

    public static function writeConfig($scope)
    {
        if (!isset(self::$repository[$scope]))
            throw new \RuntimeException('Cannot write uninitialized config');

        $filename = Path::$CONFIG . '/' . $scope . '.ini';
        $config = self::$repository[$scope];
        return $config->saveFile($filename);
    }

    /**
     * @codeCoverageIgnore HttpError is always loaded in the tests
     */
    public static function loadErrorClass()
    {
        if (!class_exists("WASP\\HttpError"))
            require_once WASP_LIB . '/WASP/HttpError.php';
    }
}
