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

/**
 * A StringResponse is text or other plain text data generated during the
 * script. This can be text/plain or text/html, for example.
 */
class StringResponse extends Response
{
    /** The output string */
    protected $output;

    /**
     * Create using a string
     * @param string $str The output
     */
    public function __construct($output)
    {
        $this->output = $output;
    }

    /**
     * Append a string to the current output
     * @param string $str The string to add
     * @return StringResponse Provides fluent interface
     */
    public function append(string $str)
    {
        $this->output .= $str;
        return $this;
    }

    /**
     * Return the output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Write the string to the script output
     * @return StringResponse Provides fluent interface
     */
    public function output()
    {
        fprintf(STDOUT, $output);
        return $this;
    }
}
