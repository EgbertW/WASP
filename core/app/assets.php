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

$path = implode("/", WASP\Request::$url_args->getAll());

$qpos = strpos($path, "?");
$query = null;
if ($qpos !== false)
{
    $query = substr($path, $qpos + 1);
    $qparts = explode("&", $query);

    $query = array();
    foreach ($qparts as $part)
    {
        $eqpos = strpos($part, "=");
        if ($eqpos !== false)
        {
            list($k, $v) = explode("=", $part);
            $query[$k] = $v;
        }
        else
        {
            $query[] = $part;
        }
    }

    $path = substr($path, 0, $query);
}

$extpos = strrpos($path, ".");
$ext = null;
if ($extpos !== false)
    $ext = strtolower(substr($path, $extpos + 1));

$full_path = WASP\File\Resolve::asset($path);

if ($path)
{
    if ($ext === "css")
        $mime = "text/css";
    elseif ($ext === "js")
        $mime = "text/javascript";
    else
        $mime = mime_content_type($full_path);

    header("Content-type: " . $mime);
    $h = fopen($full_path, "r");
    fpassthru($h);
    fclose($h);
    die();
}
throw new WASP\HttpError(404, "File {$path} could not be found!");
