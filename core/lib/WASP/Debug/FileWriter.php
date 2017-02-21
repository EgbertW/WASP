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

namespace WASP\Debug;

use Psr\Log\LogLevel;
use WASP\Request;
use WASP\Util\File;

class FileWriter implements LogWriterInterface
{
    private $filename;
    private $min_level;
    private $file = null;

    public function __construct($filename, $min_level = LogLevel::DEBUG)
    {
        $this->filename = $filename;
        $this->min_level = Logger::getLevelNumeric($min_level);
    }

    public function write(string $level, $message, array $context)
    {
        $lvl_num = Logger::getLevelNumeric($level);
        if ($lvl_num < $this->min_level)
            return;

        $message = Logger::fillPlaceholders($message, $context);
        $module = isset($context['_module']) ? $context['_module'] : "";
        $fmt = "[" . date('Y-m-d H:i:s') . '][' . $module . ']';
        
        if (class_exists(Request::class, false))
        {
            if (isset(Request::$remote_ip))
                $fmt .= '[' . Request::$remote_ip . ']';

            if (!empty(Request::$remote_host) && Request::$remote_host !== Request::$remote_ip)
                $fmt .= '[' . Request::$remote_host . ']';
        }

        $fmt .= ' ' . strtoupper($level) . ': ';

        $fmt .= ' ' . $message;
        $this->writeLine($fmt);
    }

    private function writeLine(string $str)
    {
        $new_file = false;
        if (!$this->file)
        {
            $f = new File($this->filename);
            $f->touch();
            $this->file = fopen($this->filename, 'a');
        }

        if ($this->file)
            fwrite($this->file, $str . "\n");
    }
}
