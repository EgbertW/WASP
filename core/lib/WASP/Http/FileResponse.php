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

namespace WASP\Http;

/**
 * Output a file, given its filename. The handler may decide to output the
 * file using X-Send-File header, or by opening the file and passing the
 * contents.
 */
class FileOutput extends Response
{
    /** The filename of the file to send */
    protected $filename;

    /** The filename for the file that is sent to the client */
    protected $output_filename;

    /** Whether to sent as download or embedded */
    protected $download;

    /**
     * Create the response using the file name
     * @param string $filename The file to load / send
     * @param string $output_filename The filename to use in the output
     */
    public function __construct(string $filename, string $output_filename = "", bool $download = false)
    {
        $this->filename = $filename;
        if ($output_filename === null)
            $output_filename = basename($this->filename);
    }

    /**
     * @return string The path of the file to sent
     */
    public function getFileName()
    {
        return $this->filename;
    }

    /** 
     * @return string The filename to sent to the client
     */
    public function getOutputFileName()
    {
        return $this->output_filename;
    }

    /**
     * @return string The mime-type. Automatically determined by checking the file
     */
    public function getMime()
    {
        $extpos = strrpos($this->filename, ".");
        $ext = null;
        if ($extpos !== false)
            $ext = strtolower(substr($this->filename, $extpos + 1));

        if ($ext === "css")
            $mime = "text/css";
        elseif ($ext === "js")
            $mime = "text/javascript";
        else
            $mime = mime_content_type($this->filename);

        return $mime;
    }

    /**
     * @return bool True if the file should be presented as download, false if
     *              the browser may render it directly
     */
    public function getDownload()
    {
        return $this->download;
    }
}
