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
    use WASP\File\Resolve;

    class Template
    {
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
                self::$log = new Debug\Log("WASP.Template");

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
            $path = File\Resolve::template($name);

            if ($path === null)
                throw new HttpError(500, "Template file could not be found: " . $name);
            return $path;
        }

        public function render()
        {
            extract($this->arguments);
            $language = Request::$language;
            $config = Config::getConfig('main', true);
            $dev = $config === null ? true : $config->get('site', 'dev');
            $cli = array_key_exists('argv', $_SERVER);

            ob_start();
            include $this->path;
            $output = ob_get_contents();
            ob_end_clean();

            if ($this->mime !== null)
            {
                header("Content-type: $this->mime");
                echo $output;
            }
            else
            {
                throw new HttpError(400, "No supported response type requested");
            }

            self::$log->debug("WASP.Template", "*** Finished processing {} request to {}", Request::$method, Request::$uri);
            exit();
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
            $priority = Request::isAccepted($mime);
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

        public function writeJSON(array $data)
        {
            WASP\JSON::init();
            WASP\JSON::add($data);
            WASP\JSON::output();
        }

        public function writeXML(array $data, $root = "Response")
        {
            $writer = \XMLWriter::openMemory();
            $writer->startDocument();

            $this->startElement($root);
            $this->writeXMLRecursive($writer, $data);
            $this->endElement();
            
            $writer->endDocument();
            $writer->outputMemory();
        }

        public function writeXMLRecursive(\XMLWriter $writer, $data)
        {
            foreach ($data as $key => $value)
            {
                if (substr($key, 0, 1) == "_")
                {
                    $writer->writeAttribute(substr($key, 1), (string)$value); 
                }
                else
                {
                    $writer->startElement($key);
                    if (is_array($value))
                        $this->writeXMLRecursive($writer, $value);
                    else
                        $this->text(Debug\Log::str($value));
                    $writer->endElement();
                }
            }
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
            $dev = $cfg->get('site', 'dev', true);
            foreach (self::$js as $l)
            {
                $relpath = "js/" . $l;
                $devpath = $relpath . ".js";
                $prodpath = $relpath . ".min.js";
                self::$log->info("Development js path: {}", $devpath);
                self::$log->info("Production js path: {}", $prodpath);
                $dev_file = Resolve::asset($devpath);
                $prod_file = Resolve::asset($prodpath);
                self::$log->info("Development js file: {}", $dev_file);
                self::$log->info("Production js file: {}", $prod_file);

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
            $dev = $cfg->get('site', 'dev', true);
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
}

namespace 
{
    function tpl($name)
    {
        $tpl = WASP\Template::$last_template->resolve($name);
        return $tpl;
    }

    function tl()
    {
        $texts = func_get_args();
        $langs = WASP\Template::$last_template->translations;

        if (count($texts) < count($langs))
            $langs = array_slice($langs, 0, count($texts));
        elseif (count($texts) > count($langs))
            $texts = array_slice($texts, 0, count($langs));

        $translations = array_combine($langs, $texts);
        $language = WASP\Request::$language;

        $text = isset($translations[$language]) ? $translations[$language] : reset($translations);

        //if (strpos(WASP\Template::$last_template->mime, "text/html") !== false)
        //    return htmlentities($text);
        return $text;
    }

    function js($script)
    {
        WASP\Template::registerJS($script);
    }

    function css($stylesheet)
    {
        WASP\Template::registerCSS($stylesheet);
    }
}
