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
        private $arguments = array();
        public $dir;
        public $path;
        public $translations = array();
        public static $last_template = null;
        public $mime = null;

        public function __construct($name)
        {
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

            Debug\debug("WASP.Template", "*** Finished processing {} request to {}", Request::$method, Request::$uri);
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
            echo json_encode($data);
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
}
