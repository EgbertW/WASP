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

use WASP\File\Resolve;
use WASP\DB\DB;

class Request
{
    public static $CLI_MIME = array(
        'text/plain' => 1.0,
        'text/html' => 0.9,
        'application/json' => 0.8,
        'application/xml' => 0.7,
        '*/*' => 0.5
    );

    public static $host;
    public static $uri;
    public static $app;
    public static $route;
    public static $query;
    public static $protocol;
    public static $secure;
    public static $ajax;
    public static $get;
    public static $post;
    public static $url_args;
    public static $cookie;
    public static $session;
    public static $method;
	public static $accept = array();

    public static $remote_ip;
    public static $remote_host;

    public static $language;
    public static $domain;
    public static $subdomain;
    
    public static function setupSession()
    {
        $conf = Config::getConfig();
        
        $domain = self::$domain;
        $sub = self::$subdomain;

        $lifetime = $conf->get('cookie', 'lifetime', 30 * 24 * 3600);
        $path = '/';
        $domain = (!empty($sub) && $sub !== "www") ? $sub . "." . $domain : $domain;
        $secure = self::$secure;
        $httponly = $conf->get('cookie', 'httponly', true) == true;
        $session_name = (string)$conf->get('cookie', 'prefix', 'cms_') . str_replace(".", "_", $domain);

        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        session_name($session_name);
        session_start();

        // Make sure the cookie expiry is updated every time.
        setcookie(session_name(), session_id(), time() + $lifetime);

        self::$session = new Arguments($_SESSION);
        if (self::$session->has('language'))
            self::$language = self::$session->get('language');
    }

    public static function dispatch()
    {
        self::$host = $_SERVER['HTTP_HOST'];
        self::$uri = $_SERVER['REQUEST_URI'];
        self::$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on";
        self::$protocol = self::$secure ? "https://" : "http://";
        self::$ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        if (isset($_GET['ajax']) || isset($_POST['ajax']))
            self::$ajax = true;

        self::$remote_ip = $_SERVER['REMOTE_ADDR'];
        self::$remote_host = gethostbyaddr(self::$remote_ip);

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;
        if (empty($accept))
            $accept = "text/html";

		$accept = explode(",", $accept);
		foreach ($accept as $type)
		{
			if (preg_match("/^([^;]+);q=([\d.]+)$/", $type, $matches))
			{
				$type = $matches[1];
				$prio = (float)$matches[2];
			}
			else
				$prio = 1.0;

			self::$accept[$type] = $prio;
		}
		if (count(self::$accept) === 0)
			throw new HttpError(400, "No accept type requested");

        // Get request data
        self::$get = new Arguments($_GET);
        self::$post = new Arguments($_POST);
        self::$cookie = new Arguments($_COOKIE);
        self::$method = $_SERVER['REQUEST_METHOD'];

        Util\Redirection::checkRedirect();
        self::setupSession();

        $qpos = strpos(self::$uri, "?");
        if ($qpos !== false)
        {
            self::$query = substr(self::$uri, $qpos);
            self::$uri = substr(self::$uri, 0, $qpos);
        }

        $resolved = Resolve::app(self::$uri);
        if ($resolved === null)
            throw new HttpError(404, 'Could not resolve ' . self::$uri);

        self::$route = $resolved['route'];
        self::$url_args = new Arguments($resolved['remainder']);
        self::$app = $resolved['path'];

        self::execute($resolved['path']);
    }

    private static function execute($path)
    {
        // Prepare some variables that come in handy in apps
        $config = Config::getConfig();
        $db = DB::get();
        $get = self::$get;
        $post = self::$post;
        $url_args = self::$url_args;

        Debug\debug("WASP.Request", "Including {}", $path);
        include $path;

        if (Template::$last_template === null)
            throw new HttpError(400, self::$uri);
    }

    public static function setErrorHandler()
    {
        // Don't attach error handlers when running from CLI
        if (self::cli())
        {
            ini_set('error_reporting', E_ALL);
            ini_set('display_errors', 'On');
            return;
        }

        // We're processing a HTTP request, so set up Exception handling for better output
        set_exception_handler(array("WASP\\Request", "handleException"));
        set_error_handler(array("WASP\\Request", "handleError"), E_ALL | E_STRICT);
    }

    public static function handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        Debug\error("WASP.Request", "PHP Error {}: {} on {}({})", $errno, $errstr, $errfile, $errline);
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function handleException($exception)
    {
        if (!headers_sent())
            header("Content-type: text/plain");

        $tpl = "error/httperror";
        $code = 500;
        if ($exception instanceof \WASP\HttpError)
        {
            $code = $exception->getCode();

            try
            {
                $tpln = "error/http" . $exception->getCode();
                $path = Resolve::template($tpln);
                if ($path !== null)
                    $tpl = $tpln;
            }
            catch (Exception $e2)
            {
                Debug\error("WASP.Request", "Exception while resolving error template: {}: {}", get_class($exception), $exception->getMessage());
            }
        }
        else
        {
            $class = get_class($exception);
            $parts = explode("\\", $class);
            $class = end($parts);
            $tpln = "error/" . $class;
            $fn = $tpln . ".php";

            $path = Resolve::template($tpln);
            if ($path !== null)
                $tpl = $tpln;
        }

        if (!headers_sent())
            http_response_code($code);

        Debug\error("WASP.Request", "Exception: {}: {}", get_class($exception), $exception->getMessage());
        Debug\error("WASP.Request", "In: {}({})", $exception->getFile(), $exception->getLine());
        Debug\error("WASP.Request", $exception->getTraceAsString());
        Debug\info("WASP.Request", "*** [{}] Failed processing {} request to {}", $code, self::$method, self::$uri);

        try
        {
            $tpln = $tpl;
            $tpl = new Template($tpl);
            $tpl->assign('exception', $exception);
            $tpl->render();
        }
        catch (HttpError $ex)
        {
            Debug\critical("WASP.Request", "An exception of type {} (code: {}, message: {}) occurred. Additionally, the error template ({}) cannot be loaded", get_class($exception), $exception->getCode(), $exception->getMessage(), $tpln);
            Debug\critical("WASP.Request", "The full stacktrace follows: {}", $exception);
            Debug\critical("WASP.Request", "The full stacktrace of the failed template is: {}", $ex);
            if (!headers_sent())
                header("Content-type: text/html");

            echo "<!doctype html><html><head><title>Internal Server Error</title></head>";
            echo "<body><h1>Internal Server Error</h1>";

            $dev = false;
            try
            {
                $conf = Config::getConfig();
                $dev = $conf->get('site', 'dev', false);
            }
            catch (\Exception $e)
            {}

            if ($dev)
                echo "<pre>" . Debug\Log::str($exception) . "</pre>";
            else
                echo "<p>Something is going wrong on the server. Please check back later - an administrator will have been notified</p>";
            if (method_exists($exception, 'getUserMessage'))
                echo "<p>Explanation: " . htmlentities($exception->getUserMessage()) . "</p>";
            echo "</body></html>";

            die();
        }
    }

    public static function isAccepted($mime)
    {
        if (empty(self::$accept))
            return true;

        foreach (self::$accept as $type => $priority)
        {
            if (strpos($type, "*") !== false)
            {
                $regexp = "/" . str_replace("WC", ".*", preg_quote(str_replace("*", "WC", $type), "/")) . "/i";
                if (preg_match($regexp, $mime))
                    return $priority;
            }
            elseif (strtolower($mime) === strtolower($type))
                return $priority;
        }
        return false;
    }

    public static function cli()
    {
        return PHP_SAPI === "cli";
    }

	public static function getBestResponseType(array $types)
	{
        $best_priority = null;
        $best_type = null;

        if (self::cli())
            self::$accept = self::$CLI_MIME;

        foreach ($types as $type)
        {
            $priority = Request::isAccepted($type);
            if ($priority === false)
                continue;

            if ($best_priority === null || $priority > $best_priority)
            {
                $best_priority = $priority;
                $best_type = $type;
            }
        }

        return $best_type;
	}

	public function outputBestResponseType(array $available)
	{
		$types = array_keys($available);
		$type = self::getBestResponseType($types);
		
		header("Content-type: " . $type);
		echo $available[$type];
	}
}
