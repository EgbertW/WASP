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

use PHPUnit\Framework\TestCase;
use WASP\Autoload\Resolve;
use WASP\System;
use WASP\Path;
use WASP\Dictionary;

/**
 * @covers WASP\Http\Request
 */
final class RequestTest extends TestCase
{
    private $get;
    private $post;
    private $server;
    private $cookie;
    private $config;

    private $path;
    private $resolve;

    public function setUp()
    {
        $this->get = array(
            'foo' => 'bar'
        );

        $this->post = array(
            'test' => 'value'
        );

        $this->server = array(
            'REQUEST_SCHEME' => 'https',
            'SERVER_NAME' => 'www.example.com',
            'REQUEST_URI' => '/foo',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => 'POST',
            'HTTP_ACCEPT' => 'text/plain;q=1,text/html;q=0.9'
        );

        $this->cookie = array(
            'session_id' => '1234'
        );

        $config = array(
            'site' => array(
                'url' => 'http://www.example.com',
                'language' => 'en'
            ),
            'cookie' => array(
                'lifetime' => 60,
                'httponly' => true
            )
        );
        $this->config = new Dictionary($config);

        $this->path = System::path();
        $this->resolve = new Resolve($this->path);
    }

    /**
     * @covers WASP\Http\Request::__construct
     * @covers WASP\Session::start
     */
    public function testRequestVariables()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);

        $this->assertEquals($req->get->getAll(), $this->get);
        $this->assertEquals($req->post->getAll(), $this->post);
        $this->assertEquals($req->cookie->getAll(), $this->cookie);
        $this->assertEquals($req->server->getAll(), $this->server);
        $this->assertEquals($req->session->getAll(), $_SESSION);

        $this->get['foobarred_get'] = true;
        $this->assertEquals($req->get->getAll(), $this->get);

        $this->post['foobarred_post'] = true;
        $this->assertEquals($req->post->getAll(), $this->post);

        $this->cookie['foobarred_cookie'] = true;
        $this->assertEquals($req->cookie->getAll(), $this->cookie);

        $this->server['foobarred_server'] = true;
        $this->assertEquals($req->server->getAll(), $this->server);

        $_SESSION['foobarred_session'] = true;
        $this->assertEquals($req->session->getAll(), $_SESSION);

        //public static function dispatch()
        //public static function handleException($exception)
        //public static function getBestResponseType(array $types)
        //public function outputBestResponseType(array $available)
    }

    /**
     * @covers WASP\Http\Request::__construct
     * @covers WASP\Session::start
     */
    public function testRouting()
    {
        $this->server['SERVER_NAME'] = 'www.example.com';
        $this->server['REQUEST_URI'] = '/foo';
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $this->assertEquals($req->route, '/');

        $this->server['REQUEST_URI'] = '/assets';
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $this->assertEquals($req->route, '/assets');
    }

    /**
     * @covers WASP\Http\Request::__construct
     * @covers WASP\Http\Request::findVirtualHost
     * @covers WASP\Http\Request::handleUnknownHost
     */
    public function testRoutingInvalidHostIgnorePolicy()
    {
        $this->server['REQUEST_SCHEME'] = 'https';
        $this->server['SERVER_NAME'] = 'www.example.nl';
        $this->server['REQUEST_URI'] = '/assets';
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $this->assertEquals($req->route, '/assets');
    }

    /**
     * @covers WASP\Http\Request::__construct
     * @covers WASP\Http\Request::findVirtualHost
     * @covers WASP\Http\Request::handleUnknownHost
     */
    public function testRoutingInvalidHostErrorPolicy()
    {
        $this->server['REQUEST_SCHEME'] = 'https';
        $this->server['SERVER_NAME'] = 'www.example.nl';
        $this->server['REQUEST_URI'] = '/assets';
        $this->config['site']['unknown_host_policy'] = 'ERROR';
        $this->expectException(Error::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Not found');
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
    }

    /**
     * @covers WASP\Http\Request::__construct
     * @covers WASP\Http\Request::findVirtualHost
     * @covers WASP\Http\Request::handleUnknownHost
     */
    public function testRoutingRedirectHost()
    {
        $this->server['REQUEST_SCHEME'] = 'https';
        $this->server['SERVER_NAME'] = 'www.example.nl';
        $this->server['REQUEST_URI'] = '/assets';
        $this->config['site'] = 
            array(
                'url' => array(
                    'http://www.example.com',
                    'http://www.example.nl'
                ),
                'language' => array('en'),
                'redirect' => array(1 => 'http://www.example.com/')
            );

        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
    }

    /**
     * @covers WASP\Http\Request::findBestMatching
     * @covers WASP\Http\Request::handleUnknownHost
     */
    public function testNoSiteConfig()
    {
        $this->server['REQUEST_SCHEME'] = 'https';
        $this->server['SERVER_NAME'] = 'www.example.nl';
        $this->server['REQUEST_URI'] = '/assets';
        $this->config['site'] = array();

        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $this->assertEquals($this->get, $req->get->getAll());
    }

    /**
     * @covers WASP\Http\Request::parseAccept
     */
    public function testAcceptParser()
    {
        unset($this->server['HTTP_ACCEPT']);
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $this->assertEquals($req->accept, array('text/html' => 1.0));

        $accept = Request::parseAccept("garbage");
        $this->assertEquals($accept, array("garbage" => 1.0));
    }

    /**
     * @covers WASP\Http\Request::cli
     */
    public function testCLI()
    {
        $this->assertTrue(Request::cli());
    }

    /**
     * @covers WASP\Http\Request::isAccepted
     * @covers WASP\Http\Request::getBestResponseType
     */
    public function testAccept()
    {
        $request = new Request($this->get, $this->post, $this->cookie, $this->server, $this->config, $this->path, $this->resolve);
        $request->accept = array(); 
        $this->assertTrue($request->isAccepted("text/html") == true);
        $this->assertTrue($request->isAccepted("foo/bar") == true);

        $request->accept = array(
            'text/html' => 0.9,
            'text/plain' => 0.8,
            'application/*' => 0.7
        );

        $this->assertTrue($request->isAccepted("text/html") == true);
        $this->assertTrue($request->isAccepted("text/plain") == true);
        $this->assertTrue($request->isAccepted("application/bar") == true);
        $this->assertFalse($request->isAccepted("foo/bar") == true);

        $resp = $request->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/html");

        $resp = $request->getBestResponseType(array('application/bar', 'application/foo'));
        $this->assertEquals($resp, "application/bar");

        $resp = $request->getBestResponseType(array('application/foo', 'application/bar'));
        $this->assertEquals($resp, "application/foo");

        $request->accept = array();
        $resp = $request->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/plain");

        $op = array(
            'text/plain' => 'Plain text',
            'text/html' => 'HTML Text'
        );

        ob_start();
        $request->outputBestResponseType($op);
        $c = ob_get_contents();
        ob_end_clean();

    }
}
