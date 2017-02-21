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

        public static $last_template = null;

        private $request;
        private $path;
        private $arguments = array();
        private $template_path;
        private $resolve;
        public $translations = array();
        public $mime = null;


        public function __construct($name)
        {
            if (file_exists($name))
                $tpl = $name;
            else
                $tpl = $this->resolve->template($name);
            
            $this->template_path = $tpl;
            if (!file_exists($this->template_path))
                throw new HttpError(500, "Template does not exist: " . $name);

            self::$last_template = $this;
            $this->request = Request::current();
            $this->path = $this->request->path;
            $this->resolve = $this->request->resolver;
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
            $result = $this->renderReturn();
            if (!$result instanceof Response)
                if ($result instanceof Throwable)
                    throw new HttpError(500, "Did not get a proper response", $result);
                else
                    throw new HttpError(500, "Did not get any proper response");

            throw $result;
        }

        public function renderReturn()
        {
            extract($this->arguments);
            $request = $this->request;
            $language = $request->language;
            $config = Config::getConfig('main', true);
            $dev = $config === null ? false : $config->get('site', 'dev');
            $cli = Request::CLI();

            try
            {
                ob_start();
                include $this->template_path;
                $output = ob_get_contents();
                ob_end_clean();
                $response = new StringResponse($output);
                return $response;
            }
            catch (Response $e)
            {
                $response->setMime($this->mime);
                self::$logger->debug("*** Finished processing {0} request to {1} with {2}", [Request::$method, Request::$uri, get_class($e)]);
                return $e; 
            }
            catch (TerminateRequest $e)
            {
                self::$logger->debug("*** Finished processing {0} request to {1} with terminate request", [Request::$method, Request::$uri]);
                return $e;
            }
            catch (Throwable $e)
            {
                self::$logger->debug("*** Finished processing {0} request to {1}", [$request->method, $request->url]);
                return new HttpError(500, "Template threw exception", "", $e);
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
            $best = Request::current()->getBestResponseType($types);

            // Set the mime-type to the best selected output
            $charset = (substr($best, 0, 5) == "text/") ? "utf-8" : null;
            $this->want($best, $charset);

            return $best;
        }

        public function setLanguageOrder()
        {
            $this->translations = func_get_args();
        }

        public function addJS($script)
        {
            $mgr = $this->request->getResponseBuilder()->getAssetManager();
            $mgr->addScript($script);
            return $this;
        }

        public function addCSS($stylesheet)
        {
            $mgr = $this->request->getResponseBuilder()->getAssetManager();
            $mgr->addCSS($stylesheet);
            return $this;
        }

        public function insertJS()
        {
            $mgr = $this->request->getResponseBuilder()->getAssetManager();
            return $mgr->injectScript();
        }

        public function insertCSS()
        {
            $mgr = $this->request->getResponseBuilder()->getAssetManager();
            return $mgr->injectCSS();
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
                self::$logger->info("Development js file: {0}", [$dev_file]);
                self::$logger->info("Production js file: {0}", [$prod_file]);

                if ($dev && $dev_file)
                    $list[] = "/assets/" .$devpath;
                elseif ($prod_file)
                    $list[] = "/assets/" . $prodpath;
                elseif ($dev_file)
                    $list[] = "/assets/" . $devpath;
                else
                    self::$logger->error("Requested javascript {} could not be resolved", $l);
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
                    self::$logger->error("Requested stylesheet {} could not be resolved", $l);
            }
            return $list;
        }

        public static function findExceptionTemplate(Throwable $exception)
        {
            $class = get_class($exception);
            $code = $exception->getCode();

            $resolved = null;
            while ($class)
            {
                $path = 'error/' . str_replace('\\', '/', $class);
                if ($class === "WASP\\Http\\Error")
                    $path = 'error/HttpError'; 

                if (!empty($code))
                {
                    $resolved = Resolve::template($path . $code);
                    if ($resolved)
                        break;
                }

                $resolved = Resolve::template($path);
                if ($resolved)
                    break;

                $class = get_parent_class($class);
            }

            if (!$resolved)
                throw new \RuntimeException("Could not find any matching template for " . get_class($exception));
            
            return $resolved ? new Template($resolved) : null;
        }
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

    function txt($str)
    {
        return htmlentities($str, ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES);
    }
}
