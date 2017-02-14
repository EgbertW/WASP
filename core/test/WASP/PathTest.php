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
 * @covers WASP\HttpError
 */
final class PathTest extends TestCase
{
    private $wasproot;

    public function setUp()
    {
        $this->wasproot = dirname(dirname(dirname(dirname(realpath(__FILE__)))));
    }

    public function tearDown()
    {
        $this->wasproot = dirname(dirname(dirname(dirname(realpath(__FILE__)))));
        Path::setup($this->wasproot);
    }

    /**
     * @covers WASP\Path::setup
     */
    public function testPath()
    {
        $path = $this->wasproot;
        Path::setup($path);
        $this->assertEquals($path, Path::$ROOT);
        $this->assertEquals($path . '/config', Path::$CONFIG);
        $this->assertEquals($path . '/sys', Path::$SYS);
        $this->assertEquals($path . '/var', Path::$VAR);
        $this->assertEquals($path . '/var/cache', Path::$CACHE);

        $this->assertEquals($path . '/http', Path::$HTTP);
        $this->assertEquals($path . '/http/assets', Path::$ASSETS);
        $this->assertEquals($path . '/http/assets/js', Path::$JS);
        $this->assertEquals($path . '/http/assets/css', Path::$CSS);
        $this->assertEquals($path . '/http/assets/img', Path::$IMG);

        Path::setup($path, $path . '/var');
        $this->assertEquals($path, Path::$ROOT);
        $this->assertEquals($path . '/config', Path::$CONFIG);
        $this->assertEquals($path . '/sys', Path::$SYS);
        $this->assertEquals($path . '/var', Path::$VAR);
        $this->assertEquals($path . '/var/cache', Path::$CACHE);

        $this->assertEquals($path . '/var', Path::$HTTP);
        $this->assertEquals($path . '/var/assets', Path::$ASSETS);
        $this->assertEquals($path . '/var/assets/js', Path::$JS);
        $this->assertEquals($path . '/var/assets/css', Path::$CSS);
        $this->assertEquals($path . '/var/assets/img', Path::$IMG);
    }

    /**
     * @covers WASP\Path::setup
     */
    public function testExceptionRootInvalid()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Root does not exist");
        Path::setup('/tmp/non/existing/dir', '/tmp/another/non/existing/dir');
    }

    /**
     * @covers WASP\Path::setup
     */
    public function testExceptionWebrootInvalid()
    {
        $path = $this->wasproot;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Webroot does not exist");
        Path::setup($path, '/tmp/non/existing/dir');
    }
}
