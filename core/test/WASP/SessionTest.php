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

use DateTime;
use DateInterval;

/**
 * @covers WASP\Session
 */
final class SessionTest extends TestCase
{
    private $vhost;
    private $config;

    public function setUp()
    {
        $this->vhost = new VirtualHost('http://www.foobar.com', null);
        $this->config = new Dictionary();
        $this->server_vars = new Dictionary();
        $this->server_vars['HTTP_USER_AGENT'] = 'MockUserAgent';
        $this->server_vars['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function tearDown()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();
    }

    public function testSession()
    {
        $a = new Session($this->vhost, $this->config, $this->server_vars);
        $a->startHttpSession();
         
        $cookie = $a->getCookie();
        $this->assertEquals('wasp_www_foobar_com', $cookie->getName());

        $expires = new \DateTime("@" . $cookie->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertEquals('www.foobar.com', $cookie->getDomain());
        $this->assertEquals(true, $cookie->GetHttpOnly());
    }

    public function testSessionReset()
    {
        $a = new Session($this->vhost, $this->config, $this->server_vars);
        $a->startHttpSession();

        $cookie = $a->getCookie();
        $old_session_id = $cookie->getValue();
         
        $a->resetID();
        $cookie = $a->getCookie();
        $new_session_id = $cookie->getValue();

        $this->assertFalse($old_session_id === $new_session_id);
        $this->assertEquals($_SESSION, $a->getAll());
        
        $mgmt = $a['session_mgmt']->getAll();
    }

    public function testSessionDestroy()
    {
        $a = new Session($this->vhost, $this->config, $this->server_vars);
        $a->startHttpSession();

        $a['pi'] = 3.14;

        $this->assertEquals(3.14, $_SESSION['pi']);
        $a->destroy();
        $this->assertFalse(isset($_SESSION['pi']));

        $this->assertEquals(PHP_SESSION_NONE, session_status());
    }

    public function testSessionConfigWithLifetime()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '1D');
        $a = new Session($this->vhost, $cfg, $this->server_vars);
        $a->startHttpSession();

        $c = $a->getCookie();

        $now = new DateTime();

        $day2 = new DateTime();
        $offs = new DateInterval('P2D');
        $day2->add($offs);

        $expires = new DateTime('@' . $c->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertTrue(Date::isBefore($expires, $day2));
    }

    public function testSessionConfigWithLifetimeIntValue()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '86400');
        $a = new Session($this->vhost, $cfg, $this->server_vars);
        $a->startHttpSession();

        $c = $a->getCookie();

        $now = new DateTime();

        $day2 = new DateTime();
        $offs = new DateInterval('P2D');
        $day2->add($offs);

        $expires = new DateTime('@' . $c->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertTrue(Date::isBefore($expires, $day2));
    }

    public function testCLISession()
    {
        $a = new Session($this->vhost, $this->config, $this->server_vars);
        $a->StartCLISession();

        $a['test'] = 3.14;
        $_SESSION['test2'] = 6.28;
        $this->assertEquals($a->getAll(), $_SESSION);
    }
}
