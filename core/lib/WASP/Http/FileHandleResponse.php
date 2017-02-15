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
 * Output a file, given an opened file handle. 
 */
class FileHandleOutput extends Response
{
    /** The file handle from which to read the data */
    protected $filehandle;

    /** The mime type of the data that is being sent */
    protected $mime;

    /** The filename for the file that is sent to the client */
    protected $output_filename;

    /** Whether to sent as download or embedded */
    protected $download;

    /**
     * Create the response using the file name
     * @param string $filehandle The open file handle to pass through to the client
     * @param string $output_filename The filename to use in the output
     * @param string $mime The mime-type to use for the transfer
     */
    public function __construct($filehandle, string $output_filename, string $mime, bool $download = false)
    {
        $this->filehandle = $filehandle;
        $this->output_filename = $filename;
        $this->mime = $mime;
        $this->download = $download;
    }

    /**
     * @return resource The opened file handle that should be passed to the client
     */
    public function getFileHandle()
    {
        return $this->filehandle;
    }

    /** 
     * @return string The filename to sent to the client
     */
    public function getOutputFileName()
    {
        return $this->output_filename;
    }

    /**
     * @return string The mime-type for the file.
     */
    public function getMime()
    {
        return $this->mime;
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
