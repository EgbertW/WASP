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

use WASP\Autoload\Resolve;
use WASP\Http\ResponseHookInterface;
use WASP\Http\Request;
use WASP\Http\Response;
use WASP\Http\StringResponse;
use WASP\Debug\LoggerAwareStaticTrait;

class AssetManager implements ResponseHookInterface
{
    use LoggerAwareStaticTrait;

    protected $scripts = array();
    protected $css = array();
    protected $minified = true;
    protected $tidy = false;
    protected $request;
    protected $resolver;

    protected $inline_variables = array();
    protected $inline_style = array();

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->resolver = $request->getResolver();
    }
    
    public function setMinified(bool $minified)
    {
        $this->minified = $minified;
        return $this;
    }

    public function setTidy(bool $tidy)
    {
        $this->tidy = $tidy;
        return $this;
    }

    public function addScript(string $script)
    {
        $script = $this->stripSuffix($script, ".min", ".js");
        $this->scripts[$script] = array("path" => $script);
        return $this;
    }

    public function addStyle($style)
    {
        $this->inline_style[] = $style;
    }

    public function addVariable($name, $value)
    {
        $this->inline_variables[$name] = $value;
        return $this;
    }

    public function addCSS(string $stylesheet, $media = "screen")
    {
        $stylesheet = $this->stripSuffix($stylesheet, ".min", ".css");
        $this->css[$stylesheet] = array("path" => $stylesheet, "media" => $media);
        return $this;
    }
    
    private function stripSuffix($path, $suffix1, $suffix2)
    {
        if (substr($path, -strlen($suffix2)) === $suffix2)
            $path = substr($path, 0, -strlen($suffix2));
        if (substr($path, -strlen($suffix1)) === $suffix1)
            $path = substr($path, 0, -strlen($suffix1));
        return $path;
    }

    public function injectScript()
    {
        return "#WASP-JAVASCRIPT#";
    }

    public function injectCSS()
    {
        return "#WASP-CSS#";
    }

    public function resolveAssets(array $list, $type)
    {
        $urls = array();
        foreach ($list as $asset)
        {
            $relpath = $type . "/" . $asset['path'];
            $unminified_path = $relpath . "." . $type;
            $minified_path = $relpath . ".min." . $type;
            $unminified_file = $this->resolver->asset($unminified_path);
            $minified_file = $this->resolver->asset($minified_path);

            $asset_path = null;
            if (!$this->minified && $unminified_file)
                $asset_path = "/assets/" .$unminified_path;
            elseif ($minified_file)
                $asset_path = "/assets/" . $minified_path;
            elseif ($unminified_path)
                $asset_path = "/assets/" . $unminified_path;
            else
            {
                self::$logger->error("Requested asset {} could not be resolved", $asset);
                continue;
            }

            $asset['path'] = $asset_path;
            $asset['url'] = $this->request->vhost->URL($asset_path);
            $urls[] = $asset;
        }

        return $urls;
    }

    public function executeHook(Request $request, Response $response, string $mime)
    {
        if ($response instanceof StringResponse && $mime === "text/html")
        {
            $output = $response->getOutput($mime);
            $scripts = $this->resolveAssets($this->scripts, "js");
            $css = $this->resolveAssets($this->css, "css");

            $tpl = new Template('parts/scripts');
            $tpl->assign('scripts', $scripts);
            $tpl->assign('inline_js', $this->inline_variables);
            $script_html = $tpl->renderReturn()->getOutput($mime);
            $output = str_replace('#WASP-JAVASCRIPT#', $script_html, $output);

            $tpl = new Template('parts/stylesheets');
            $tpl->assign('stylesheets', $css);
            $tpl->assign('inline_css', $this->inline_style);
            $css_html = $tpl->renderReturn()->getOutput($mime);
            $output = str_replace('#WASP-CSS#', $css_html, $output);

            // Tidy up output when configured and available
            if ($this->tidy)
            {
                if (class_exists("Tidy", false))
                {
                    $tidy = new \Tidy();
                    $config = array('indent' => true, 'wrap' => 120, 'markup' => true);
                    $output = $tidy->repairString($output, $config, "utf8");
                }
                else
                    self::$logger->warning("Tidy output has been requested, but Tidy extension is not available");
            }
            $response->setOutput($output, $mime);
        }
    }
}

// @codeCoverageIgnoreStart
AssetManager::setLogger();
// @codeCoverageIgnoreEnd
