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

use WASP\Debug\LoggerAwareStaticTrait;
use WASP\File\Resolve;
use WASP\DB\DB;

class Request
{
    use LoggerAwareStaticTrait;

    public static $CLI_MIME = array(
        'text/plain' => 1.0,
        'text/html' => 0.9,
        'application/json' => 0.8,
        'application/xml' => 0.7,
        '*/*' => 0.5
    );

    public static $server;
    public static $host;
    public static $url;
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

    public static $sites = array();
    public static $vhost = null;

    private static $session_cache = null;
    
    public static function setupSession()
    {
        $conf = Config::getConfig();
        
        $domain = self::$domain;
        $sub = self::$subdomain;

        if (!self::CLI())
        {
            $lifetime = $conf->dget('cookie', 'lifetime', 30 * 24 * 3600);
            $path = '/';
            $domain = (!empty($sub) && $sub !== "www") ? $sub . "." . $domain : $domain;
            $secure = self::$secure;
            $httponly = $conf->dget('cookie', 'httponly', true) == true;
            $session_name = (string)$conf->dget('cookie', 'prefix', 'cms_') . str_replace(".", "_", $domain);

            session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
            session_name($session_name);
            session_start();

            // Make sure the cookie expiry is updated every time.
            setcookie(session_name(), session_id(), time() + $lifetime);
        }
        else
        {
            self::$session_cache = new Cache('cli-session');
            $GLOBALS['_SESSION'] = self::$session_cache->get();
        }

        self::$session = new Dictionary($GLOBALS['_SESSION']);
        if (self::$session->has('language'))
            self::$language = self::$session->get('language');
    }

    public static function dispatch()
    {
        self::$server = new Dictionary($_SERVER);
        self::$get = new Dictionary($_GET);
        self::$post = new Dictionary($_POST);
        self::$cookie = new Dictionary($_COOKIE);
        self::$method = self::$server->get('REQUEST_METHOD');

        self::$url = new URL(self::$server->get('REQUEST_URI'));
        self::$ajax = 
            self::$server->get('HTTP_X_REQUESTED_WITH') === 'xmlhttprequest' ||
            self::$get->has('ajax') || 
            self::$post->has('ajax');

        self::$remote_ip = self::$server->get('REMOTE_ADDR');
        self::$remote_host = gethostbyaddr(self::$remote_ip);
        self::$accept = self::parseAccept(self::$server->get('HTTP_ACCEPT'));

        $cfg = Config::getConfig()->getSection('site');
        $sites = self::setupSites($cfg);
        $vhost = self::findVirtualHost(self::$url, self::$sites);
        if ($vhost === null)
        {
            // Determine behaviour on unknown host
            $on_unknown = strtoupper($cfg->dget('unknown_host_policy', "IGNORE"));
            $best_matching = self::findBestMatching(self::$url, self::$sites);
            switch ($on_unknown)
            {
                case "ERROR":
                    throw new HttpErrror(404, "Not found: " . self::$url);
                case "REDIRECT":
                    $redir = $best_matching->URL(self::$url->getPath);
                    Util\Redirection::redirect($redir);
                case "IGNORE":
                default:
                    $url = new URL(self::$url);
                    $url->setPath('/')->setQuery(null)->setFragment(null);
                    self::$vhost = new VirtualHost($base, self::$default_language);
                    $best_matching->getSite()->addVirtualHost(self::$vhost);
            }
        }
        
        self::setupSession();

        $resolved = Resolve::app(self::$route);
        if ($resolved === null)
            throw new HttpError(404, 'Could not resolve ' . self::$url);

        self::$route = $resolved['route'];
        self::$url_args = new Dictionary($resolved['remainder']);
        self::$app = $resolved['path'];

        self::execute($resolved['path']);
    }

    public static function parseAccept($accept)
    {
        if (empty($accept))
            $accept = "text/html";

		$accept = explode(",", $accept);
        $accepted = array();
		foreach ($accept as $type)
		{
			if (preg_match("/^([^;]+);q=([\d.]+)$/", $type, $matches))
			{
				$type = $matches[1];
				$prio = (float)$matches[2];
			}
			else
				$prio = 1.0;

			$accepted[$type] = $prio;
		}

		if (count($accept) === 0)
			throw new HttpError(400, "No accept type requested");

        return $accepted;
    }

    public static function setupSites(Dictionary $config)
    {
        $urls = $config->getSection('url'); 
        $languages = $config->getSection('language');
        $sitenames = $config->getSection('site');
        $default_language = $config->get('default_language');
        $sites = array();

        $keys = array_keys($urls);
        foreach ($keys as $host_idx)
        {
            $url = $url[$host_idx];
            $lang = isset($languages[$host_idx]) ? $languages[$host_idx] : $default_language;
            $site = isset($sitenames[$host_idx]) ? $sitenames[$host_idx] : "default";

            if (!isset($sites[$site]))
                $sites[$site] = new Site();

            $sites->addVirtualHost(
                new VirtualHost($url, $lang)
            );
        }
        return $sites;
    }

    public static function findVirtualHost(URL $url, array $sites)
    {
        foreach ($sites as $site)
        {
            $vhost = $site->match(self::$url);
            if (self::$vhost !== null)
                return $vhost;
        }
        return null;
    }

    public static function findBestMatching(URL $url, array $sites)
    {
        $vhosts = array();
        foreach ($sites as $site)
            foreach ($site->getVirtualHosts() as $vhost)
                $vhosts[] = $vhost;

        $my_url = new URL($url);
        $my_url = $my_url->set('query', null)->set('fragment', null)->toString();

        $best_percentage = 0;
        $best_idx = null;
        foreach ($paths as $idx => $vhost)
        {
            $host = $vhost->getHost()->toString();
            similar_text($my_url, $host, $percentage);
            if ($best_idx === null || $percentage > $best_percentage)
            {
                $best_idx = $idx;
                $best_percentage = $percentage;
            }
        }
        
        if ($idx === null)
            return null;
        return $vhosts[$idx];
    }

    private static function execute($path)
    {
        // Prepare some variables that come in handy in apps
        $config = Config::getConfig();
        $db = DB::get();
        $get = self::$get;
        $post = self::$post;
        $url_args = self::$url_args;

        Debug\debug("WASP.Request", "Including {0}", [$path]);
        include $path;

        if (Template::$last_template === null)
            throw new HttpError(400, self::$url);
    }

    /**
     * @codeCoverageIgnore This will not do anything from CLI except for enabling error reporting
     */
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

    /**
     * @codeCoverageIgnore As this will stop the script, its not good for unit testing
     */
    public static function handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        Debug\error("WASP.Request", "PHP Error {0}: {1} on {2}({3})", [$errno, $errstr, $errfile, $errline]);
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * @codeCoverageIgnore As this will stop the script, its not good for unit testing
     */
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
                Debug\error("WASP.Request", "Exception while resolving error template: {0}: {1}", [get_class($exception), $exception->getMessage()]);
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

        Debug\error("WASP.Request", "Exception: {0}: {1}", [get_class($exception), $exception->getMessage()]);
        Debug\error("WASP.Request", "In: {0}({1})", [$exception->getFile(), $exception->getLine()]);
        Debug\error("WASP.Request", $exception->getTraceAsString());
        Debug\info("WASP.Request", "*** [{0}] Failed processing {1} request to {2}", [$code, self::$method, self::$url]);

        try
        {
            $tpln = $tpl;
            $tpl = new Template($tpl);
            $tpl->assign('exception', $exception);
            $tpl->render();
        }
        catch (HttpError $ex)
        {
            Debug\critical("WASP.Request", "An exception of type {0} (code: {1}, message: {2}) occurred. Additionally, the error template ({3}) cannot be loaded", [get_class($exception), $exception->getCode(), $exception->getMessage(), $tpln]);
            Debug\critical("WASP.Request", "The full stacktrace follows: {0}", [$exception]);
            Debug\critical("WASP.Request", "The full stacktrace of the failed template is: {0}", [$ex]);
            if (!headers_sent())
                header("Content-type: text/html");

            echo "<!doctype html><html><head><title>Internal Server Error</title></head>";
            echo "<body><h1>Internal Server Error</h1>";

            $dev = false;
            try
            {
                $conf = Config::getConfig();
                $dev = $conf->dget('site', 'dev', false);
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

        if (self::cli() && empty(self::$accept))
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

	public static function outputBestResponseType(array $available)
	{
		$types = array_keys($available);
		$type = self::getBestResponseType($types);
		
        if (!headers_sent())
            // @codeCoverageIgnoreStart
            header("Content-type: " . $type);
            // @codeCoverageIgnoreEnd
		echo $available[$type];
	}
}

// @codeCoverageIgnoreStart
Request::setLogger();
// @codeCoverageIgnoreEnd
