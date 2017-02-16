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

namespace WASP
{
    use Throwable;
    use WASP\Autoload\Resolve;
    use WASP\Debug\LoggerAwareStaticTrait;
    use WASP\Http\Request;
    use WASP\Http\Error as HttpError;
    use WASP\Http\Response;
    use WASP\Http\StringResponse;

    class Template
    {
        use LoggerAwareStaticTrait;

        protected static $log = null;

        private $arguments = array();
        public $dir;
        public $path;
        public $translations = array();
        public static $last_template = null;
        public $mime = null;

        public static $js = array();
        public static $css = array();

        public function __construct($name)
        {
            if (self::$log === null)
                self::$log = Debug\Logger::getLogger("WASP.Template");

            $tpl = Resolve::template($name);
            
            $this->path = $tpl;
            $this->dir = dirname($tpl);

            if (!file_exists($this->path))
                throw new HttpError(500, "Template does not exist: " . $name);

            self::$last_template = $this;
        }

        public function assign($name, $value)
        {
            $this->arguments[$name] = $value;
        }

        public function resolve($name)
        {
            $path = Resolve::template($name);

            if ($path === null)
                throw new HttpError(500, "Template file could not be found: " . $name);
            return $path;
        }

        public function render()
        {
            $result = $this->renderInternal();
            if (!$result instanceof Response)
                if ($result instanceof Throwable)
                    throw new HttpError(500, "Did not get a proper response", $result);
                else
                    throw new HttpError(500, "Did not get any proper response"))));

            throw $result;
        }

        private function renderInternal()
        {
            extract($this->arguments);
            $request = Request::current();
            $language = $request->language;
            $config = Config::getConfig('main', true);
            $dev = $config === null ? false : $config->get('site', 'dev');
            $cli = Request::CLI();

            try
            {
                ob_start();
                include $this->path;
                $output = ob_get_contents();
                ob_end_clean();
                $response = new StringResponse($output);
                return $response;
            }
            catch (Response $e)
            {
                $response->setMime($this->mime);
                self::$log->debug("*** Finished processing {0} request to {1} with {2}", [Request::$method, Request::$uri, get_class($e)]);
                return $e; 
            }
            catch (TerminateRequest $e)
            {
                self::$log->debug("*** Finished processing {0} request to {1} with terminate request", [Request::$method, Request::$uri]);
                return $e;
            }
            catch (Throwable $e)
            {
                self::$log->debug("*** Finished processing {0} request to {1}", [Request::$method, Request::$uri]);
                return new HttpError(500, "Template threw exception", $e);
            }
        }

        public function wantJSON()
        {
            return $this->want('application/json', 'utf-8');
        }

        public function wantHTML()
        {
            return $this->want('text/html', 'utf-8');
        }

        public function wantText()
        {
            return $this->want('text/plain', 'utf-8');
        }

        public function wantXML()
        {
            return $this->want('application/xml');
        }

        public function want($mime, $charset = null)
        {
            $priority = Request::current()->isAccepted($mime);
            if ($priority === false)
                return false;
            if (!empty($charset))
                $mime .= "; charset=" . $charset;

            $this->mime = $mime;
            return $priority;
        }

        public function chooseResponse(array $types)
        {
            $best = Request::getBestResponseType($types);

            // Set the mime-type to the best selected output
            $charset = (substr($best, 0, 5) == "text/") ? "utf-8" : null;
            $this->want($best, $charset);

            return $best;
        }

        public function setLanguageOrder()
        {
            $this->translations = func_get_args();
        }

        public static function registerJS($script)
        {
            if (substr($script, -3) === ".js")
                $script = substr($script, 0, -3);
            if (substr($script, -4) === ".min")
                $script = substr($script, 0, -4);

            if (!in_array($script, self::$js))
                self::$js[] = $script;
        }

        public static function registerCSS($stylesheet)
        {
            if (substr($stylesheet, -4) === ".css")
                $stylesheet = substr($stylesheet, 0, -4);
            if (substr($stylesheet, -4) === ".min")
                $stylesheet = substr($stylesheet, 0, -4);

            if (!in_array($stylesheet, self::$css))
                self::$css[] = $stylesheet;
        }

        public function getJS()
        {
            $list = array();
            $cfg = Config::getConfig();
            $dev = $cfg->dget('site', 'dev', true);
            foreach (self::$js as $l)
            {
                $relpath = "js/" . $l;
                $devpath = $relpath . ".js";
                $prodpath = $relpath . ".min.js";
                self::$logger->info("Development js path: {0}", [$devpath]);
                self::$logger->info("Production js path: {0}", [$prodpath]);
                $dev_file = Resolve::asset($devpath);
                $prod_file = Resolve::asset($prodpath);
                self::$log->info("Development js file: {0}", [$dev_file]);
                self::$log->info("Production js file: {0}", [$prod_file]);

                if ($dev && $dev_file)
                    $list[] = "/assets/" .$devpath;
                elseif ($prod_file)
                    $list[] = "/assets/" . $prodpath;
                elseif ($dev_file)
                    $list[] = "/assets/" . $devpath;
                else
                    self::$log->error("Requested javascript {} could not be resolved", $l);
            }
            return $list;
        }

        public function getCSS()
        {
            $list = array();
            $cfg = Config::getConfig();
            $dev = $cfg->dget('site', 'dev', true);
            foreach (self::$css as $l)
            {
                $relpath = "css/" . $l;
                $devpath = $relpath . ".css";
                $prodpath = $relpath . ".min.css";

                $dev_file = Resolve::asset($devpath);
                $prod_file = Resolve::asset($prodpath);

                if ($dev && $dev_file)
                    $list[] = "/assets/" . $devpath;
                elseif ($prod_file)
                    $list[] = "/assets/" . $prodpath;
                elseif ($dev_file)
                    $list[] = "/assets/" . $devpath;
                else
                    self::$log->error("Requested stylesheet {} could not be resolved", $l);
            }
            return $list;
        }
    }

    public function findExceptionTemplate(Throwable $exception)
    {
        $class = get_class($exception);
        $code = $exception->getCode();

        $resolved = null;
        while ($class)
        {
            $path = 'error/' . str_replace('\\', '/', $class);

            $resolved = Resolve::template($path . $code);
            if ($resolved)
                break;

            $resolved = Resolve::template($path);
            if ($resolved)
                break;

            $class = get_parent_class($class);
        }
        
        return $resolved
    }


    // @codeCoverageIgnoreStart
    Template::setLogger();
    // @codeCoverageIgnoreEnd
}

namespace 
{
    function tpl($name)
    {
        $tpl = WASP\Template::$last_template->resolve($name);
        return $tpl;
    }

    function js($script)
    {
        WASP\Template::registerJS($script);
    }

    function css($stylesheet)
    {
        WASP\Template::registerCSS($stylesheet);
    }

    function txt($str)
    {
        return htmlentities($str, ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES);
    }
}
