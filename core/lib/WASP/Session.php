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

use DateTime;
use DateInterval;
use WASP\Http\Request;
use WASP\Http\Cookie;
use WASP\Http\Error as HttpError;

class Session extends Dictionary
{
    /** Session cache object used to persist sessions in CLI sessions */
    private $session_cache = null;
    
    /** The configuration */
    private $config;

    /** The base URL of the session */
    private $url;

    /** The session cookie to be set */
    private $session_cookie = null;

    /** Session lifetime in seconds */
    private $lifetime = 0;

    /**
     * Create the session based on a VirtualHost and a configuration
     * @param WASP\VirtualHost $vhost The virtual host determining the domain
     *                                and path for the cookie
     * @param WASP\Dictionary $config The configuration for cookie parameters
     */
    public function __construct(VirtualHost $vhost, Dictionary $config)
    {
        $this->virtual_host = $vhost;
        if ($config->has('cookie', Dictionary::TYPE_ARRAY))
            $this->config = $config->get('cookie');
        else
            $this->config = $config;

        // Calculate the lifetime and expiry date
        $lifetime = $this->config->dget('lifetime', '30D');
        if (is_int_val($lifetime))
            $lifetime = 'T' . $lifetime . 'S';
        $lifetime = new DateInterval('P' . $lifetime);

        // Determine the correct expiry date
        $now = new DateTime();
        $expire = $now->add($lifetime);

        // Store the amount of seconds
        $this->lifetime = $expire->getTimestamp() - $now->getTimestamp();

        // HttpOnly should basically always be set, but allow override nonetheless
        $httponly = parse_bool($this->config->dget('httponly', true));

        $this->url = $this->virtual_host->getHost();
        $session_name = (string)$this->config->dget('prefix', 'wasp_') . str_replace(".", "_", $this->url->host);

        $this->session_cookie = new Cookie($session_name, "");
        $this->session_cookie
            ->setExpires($expire)
            ->setURL($this->url)
            ->setHttpOnly($httponly);
    }

    /**
     * Actually start the session
     */
    public function start()
    {
        if (Request::CLI())
            $this->startCLISession();
        else
            $this->startHttpSession();

        if (!$this->has('session_mgmt', 'start_time'))
            $this->set('session_mgmt', 'start_time', time());
    }

    /**
     * Provide a CLI-session by creating a cached array in $_SESSION
     */
    private function startCLISession()
    {
        $this->session_cache = new Cache('cli-session');
        $ref = &$this->session_cache->get();

        $GLOBALS['_SESSION'] = &$ref;

        // Make sure the session variables are available through this object
        $this->values = &$ref;
        $this->set('CLI', true);
    }

    /** 
     * Set up a HTTP session using cookies and the PHP session machinery
     */
    public function startHttpSession()
    {
        if (session_status() === PHP_SESSION_DISABLED)
            throw new HttpError(500, "Sesssions are disabled");

        if (session_status() === PHP_SESSION_ACTIVE)
            throw new HttpError(500, "Repeated session initialization");

        // Now do the PHP session magic to initialize the $_SESSION array
        session_set_cookie_params(
            $this->lifetime, 
            $this->session_cookie->getDomain(),
            $this->session_cookie->getPath(),
            $this->session_cookie->getSecure(),
            $this->session_cookie->getHttpOnly()
        );
        session_name($this->session_cookie->getName());
        session_start();

        // Make sure the session data is accessible through this object
        $this->values = &$_SESSION;

        // Check if session was regenerated
        $this->secureSession();

        // PHPs sessions do not renew the cookie, so it will expire after the
        // period set when the session was first created. We want to postpone
        // the session expiry at every request, so force a cookie to be sent.
        // As the session_id is available after the session started, we need to
        // update the cookie that was generated in the constructor.
        $this->session_cookie->setValue(session_id());
    }

    /**
     * Secure the session
     * 
     * This method checks if the session was destroyed, and if so, if a redirect to a new
     * session should be done. The new session ID is stored and will be sent to the client iff:
     *
     * - Less than 1 minute has passed since the previous session was destroyed
     * - The client has the same user agent and IP-address as when the session was destroyed
     *
     * If this is not the case, a new session is started and sent to the client. This method
     * also stores the current User Agent and IP address to the session.
     */
    private function secureSession()
    {
        $request = Request::current();
        $expired = false;
        if ($this->has('session_mgmt', 'destroyed'))
        {
            $when = $this->get('session_mgmt', 'destroyed');
            $ua = $this->get('session_mgmt', 'last_ua');
            $ip = $this->get('session_mgmt', 'last_ip');

            $when = new DateTime('@' . $when);
            $diff = $now->diff($when);

            $expiry = new DateInterval('P1M');
            if (Date::lessThan($expiry, $diff))
            {
                $expired = true;
            }
            elseif ($ua === $request->server['HTTP_USER_AGENT'] && $ip === $request->remote_id)
            {
                // If UA and IP match, we can redirect to the new sesssion
                // within 1 minute avoid session loss on bad connections.
                $new_session = $this->get('session_mgmt', 'new_session_id');
                if (!empty($new_session))
                {
                    session_commit(); 
                    ini_set('session.use_strict_mode', 0);
                    session_id($new_session);
                    session_start();
                    ini_set('session.use_strict_mode', 1);
                }
                else
                {
                    $expired = true;
                }
            }
        }

        if ($expired)
        {
            // Shut down session completely
            $this->clear();
            $this->set('session_mgmt', 'destroyed', 0);
            $session_id = session_create_id();
            $session->commit();

            // Start a new session
            ini_set('session.use_strict_mode', 0);
            session_id($session_id);
            session_start();
            $this->values = &$_SESSION;
            ini_set('session.use_strict_mode', 1);
            unset($this['session_mgmt']['destroyed']);
        }

        // Store the current user agent and IP address to prevent session hijacking
        $ua = $this->set('session_mgmt', 'last_ua', $request->server['REMOTE_ADDR']);
        $ip = $this->set('session_mgmt', 'last_ip', $request->server['HTTP_USER_AGENT']);

        // Check if it's time to regenerate the session ID
        if ($this->has('session_mgmt', 'start_time'))
        {
            $start = $this->getInt('session_mgmt', 'start_time');
            $start = new DateTime('@' . $start);
            $now = new DateTime();
            $elapsed = $now->diff($start);
            $interval = new DateInterval('P5D');
            if (Date::moreThan($elaped, $interval))
                $this->resetID();
        }
    }

    /** 
     * Should be called when the session ID should be changed, for example
     * after logging in or out.
     * @return WASP\Session Provides fluent interface
     */
    public function resetID()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $this->set('session_mgmt', 'destroyed', time());
            $auth = $this->get('authentication');
            if ($auth)
                unset($this['authentication']);

            $new_session_id = session_create_id();
            $this->set('session_mgmt', 'new_session_id', $new_session_id);
            session_commit();
            
            ini_set('session.use_strict_mode', 0);
            session_id($new_session_id);
            session_start();
            ini_set('session.use_strict_mode', 1);
            $this->values = &$_SESSION;
            unset($this['session_mgmt']['destroyed']);
            if ($auth)
                $this['authentication'] = $auth;

        }
    }

    /** 
     * Should be called when the session should be cleared and destroyed.
     * @return WASP\Session Provides fluent interface
     */
    public function destroy()
    {
        $this->clear();
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();
        return $this;
    }

    /** 
     * Get the session cookie to be sent to the client
     * @return WASP\Http\Cookie The session cookie
     */
    public function getCookie()
    {
        return !empty($this->session_cookie->getValue()) ? $this->session_cookie : null;
    }
}
