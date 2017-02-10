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

use PHPUnit\Framework\TestCase;

/**
 * @covers WASP\Request
 */
final class RequestTest extends TestCase
{
    /**
     * @covers WASP\TaskRunner::registerTask
     */
    public function testRequest()
    {
        //public static function setupSession()
        //public static function dispatch()
        //private static function execute($path)
        //public static function setErrorHandler()
        //public static function handleError($errno, $errstr, $errfile, $errline, $errcontext)
        //public static function handleException($exception)
        //public static function getBestResponseType(array $types)
        //public function outputBestResponseType(array $available)
    }

    /**
     * @covers WASP\Request::cli
     */
    public function testCLI()
    {
        $this->assertTrue(Request::cli());
    }

    /**
     * @covers WASP\Request::isAccepted
     * @covers WASP\Request::getBestResponseType
     */
    public function testAccept()
    {
        Request::$accept = array(); 
        $this->assertTrue(Request::isAccepted("text/html") == true);
        $this->assertTrue(Request::isAccepted("foo/bar") == true);

        Request::$accept = array(
            'text/html' => 0.9,
            'text/plain' => 0.8,
            'application/*' => 0.7
        );

        $this->assertTrue(Request::isAccepted("text/html") == true);
        $this->assertTrue(Request::isAccepted("text/plain") == true);
        $this->assertTrue(Request::isAccepted("application/bar") == true);
        $this->assertFalse(Request::isAccepted("foo/bar") == true);

        $resp = Request::getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/html");

        $resp = Request::getBestResponseType(array('application/bar', 'application/foo'));
        $this->assertEquals($resp, "application/bar");

        $resp = Request::getBestResponseType(array('application/foo', 'application/bar'));
        $this->assertEquals($resp, "application/foo");

        Request::$accept = array();
        $resp = Request::getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/plain");

        $op = array(
            'text/plain' => 'Plain text',
            'text/html' => 'HTML Text'
        );

        ob_start();
        Request::outputBestResponseType($op);
        $c = ob_get_contents();
        ob_end_clean();

        //var_dump($c);
    }
}
