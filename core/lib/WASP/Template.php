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

        private $request;
        private $path;
        private $arguments = array();
        private $title = null;
        private $template_path;
        private $resolver = null;

        public $translations = array();
        public $mime = null;

        public function __construct(Request $request)
        {
            $this->request = $request;
            $this->resolver = $request->getResolver();
            $this->path = $request->path;
        }

        public function setTemplate(string $template)
        {
            if (file_exists($template))
                $tpl = realpath($template);
            else
                $tpl = $this->resolve($template);
            
            $this->template_path = $tpl;
        }

        public function getTemplate()
        {
            return $this->template_path;
        }

        public function assign($name, $value)
        {
            $this->arguments[$name] = $value;
            return $this;
        }

        public function setTitle(string $title)
        {
            $this->title = $title;
            return $this;
        }

        public function title()
        {
            if ($this->title === null)
            {
                $site = $this->request->vhost->getSite();
                $name = $site->getName();
                $route = $this->request->route;
                if ($name !== "default")
                    $this->title = $name . " - " . $route;
                else
                    $this->title = $this->request->route;
            }

            return $this->title;
        }

        public function resolve($name)
        {
            $path = null;
            if ($this->resolver !== null)
            {
                $path = $this->resolver->template($name);
            }

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
            $config = $request->config;
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
            $priority = $this->request->isAccepted($mime);
            if ($priority === false)
                return false;
            if (!empty($charset))
                $mime .= "; charset=" . $charset;

            $this->mime = $mime;
            return $priority;
        }

        public function chooseResponse(array $types)
        {
            $best = $this->request->getBestResponseType($types);

            // Set the mime-type to the best selected output
            $charset = (substr($best, 0, 5) == "text/") ? "utf-8" : null;
            $this->want($best, $charset);

            return $best;
        }

        public function setLanguageOrder()
        {
            I18n\Translate::setLanguageOrder(func_get_args());
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

        public function setExceptionTemplate(Throwable $exception)
        {
            $class = get_class($exception);
            $code = $exception->getCode();

            $resolver = $this->resolver;
            $resolved = null;
            while ($class)
            {
                $path = 'error/' . str_replace('\\', '/', $class);
                if ($class === "WASP\\Http\\Error")
                    $path = 'error/HttpError'; 

                if (!empty($code))
                {
                    $resolved = $resolver->template($path . $code);
                    if ($resolved) break;
                }

                $resolved = $resolver->template($path);
                if ($resolved)
                    break;

                $class = get_parent_class($class);
            }

            if (!$resolved) throw new \RuntimeException("Could not find any matching template for " . get_class($exception));
            
            $this->template_path = $resolved;
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
        $request = WASP\System::getInstance()->request();
        $tpl = $request->getTemplate()->resolve($name);
        return $tpl;
    }

    function txt($str)
    {
        return htmlentities($str, ENT_HTML5 | ENT_SUBSTITUTE | ENT_QUOTES);
    }
}
