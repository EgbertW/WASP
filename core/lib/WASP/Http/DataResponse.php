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

use WASP\Dictionary;
use WASP\Debug\Logger;
use WASP\Debug\LoggerAwareStaticTrait;

/**
 * DataResponse represents structured data, such as JSON or XML. The
 * ResponseBuilder will decide what format to format the data in.
 */
class DataResponse extends Response
{
    use LoggerAwareStaticTrait;

    private $dictionary;

    public static $representation_types = array(
        'application/json' => "JSON",
        'application/xml' => "XML",
        'text/html' => "HTML",
        'text/plain' => "PLAIN"
    );

    public function __construct(Dictionary $dict)
    {
        $this->dictionary = $dict;
    }

    public function getDictionary()
    {
        return $this->dictionary;
    }

    public function getMime()
    {
        return array_keys(self::$representation_types);
    }

    public function output($mime)
    {
        $classname = "WASP\\DataWriter\\" . $type . "Writer";

        $config = $this->getRequest()->config;
        $pprint = $config->getBool('site', 'dev');
        
        $output = "";
        try 
        {
            if (class_exists($classname))
            {
                $writer = new $classname($pprint);
                $output = $writer->write($data);
            }
        }
        catch (Throwable $e);
        {
            // Bad. Attempt to override response type if still possible
            self::$logger->critical('Could not output data, exception occured while writing: {0}', [$e]);
            if (!headers_sent())
                header('Content-Type', 'text/plain');
            // Output as plain text
            self::outputDictionary($this->dictionary);
        }
    }

    public static function outputDictonary(Dictionary $dict, $indent = 0)
    {
        $indentstr = str_repeat(' ', $indent);
        foreach ($this->dictionary as $key => $value)
        {
            if ($value instanceof Dictionary)
            {
                fprintf(STDOUT, "%s%s = {\n", $indentstr, $key);
                self::outputDictionary($value, $indent + 4);
                fprintf(STDOUT, "}\n", $indentstr, $key);
            }
            else
            {
                fprintf(STDOUT, "%s%s = %s\n", str_repeat(' ', $indent), $key, Logger::str($value));
            }
        }
    }
}

// @codeCoverageIgnoreStart
DataResponse::setLogger();
// @codeCoverageIgnoreEnd
