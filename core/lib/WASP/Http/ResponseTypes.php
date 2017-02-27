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

class ResponseTypes
{
    // Common HTTP data types
    const PLAINTEXT = "text/plain";
    const HTM  = "text/html";
    const HTML = "text/html";
    const JS   = "application/javascript";
    const CSS  = "text/css";

    // Data formats
    const CSV  = "text/csv";
    const JSON = "application/json";
    const XML  = "application/xml";
    const YAML = "text/yaml";

    // Image formats
    const PNG  = "image/png";
    const JPG  = "image/jpeg";
    const JPEG = "image/jpeg";
    const GIF  = "image/gif";
    const SVG  = "image/svg+xml";

    // Audio formats
    const WAV  = "audio/wav";
    const WEBM = "audio/webm";
    const OGG  = "audio/ogg";
    const MP3  = "audio/mpeg";
    const FLAC = "audio/flac";
    const AAC  = "audio/aac";
    const M4A  = "audio/mp4";
    const WMA  = "audio/x-ms-wma";

    // Video formats
    const MP4  = "video/mp4";
    const OGV  = "video/ogg";
    const WEBV = "video/webm";
    const AVI  = "video/avi";
    const MKV  = "video/mkv";
    const MOV  = "video/quicktime";
    const WMV  = "video/x-ms-wmv";

    // Document formats
    const PDF  = "application/pdf";
    const DOC  = "application/msword";
    const XLS  = "application/vnd.ms-excel";
    const PPT  = "application/vnd.ms-powerpoint";
    const DOCX = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    const XLSX = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
    const PPTX = "application/vnd.openxmlformats-officedocument.presentationml.presentation"; 
    const ODT  = "application/vnd.oasis.opendocument.text";
    const ODS  = "application/vnd.oasis.opendocument.spreadsheet";
    const ODP  = "application/vnd.oasis.opendocument.presentation";

    // Archive formats
    const GZ   = "application/gzip";
    const XZ   = "application/x-xz";
    const TAR  = "application/x-tar";
    const BZ2  = "application/x-bzip2";
    const TGZ  = "application/gzip";
    const TBZ2 = "application/x-bzip2";
    const ZIP  = "application/zip";
    const RAR  = "application/x-rar-compressed";
    const BINARY    = "application/octet-stream";
    const MULTIPART = "multipart/form-data";

    public static function extractFromPath(string $path)
    {
        $pos = strrpos($path, '.');
        $type = null;
        if ($pos === false)
            return array(null, null);

        $ext = substr($path, $pos); 
        return self::getMimeFromExtension($ext);
    }

    public static function getMimeFromExtension(string $ext)
    {
        $uext = strtoupper(ltrim($ext, '.'));
        if (defined("static::" . $uext))
            return array($ext, constant("static::$uext"));
        return array(null, null);
    }

    public static function getFromFile(string $path)
    {
        $pos = strrpos($path, '.');
        if ($pos !== false)
        {
            $ext = substr($path, $pos + 1);
            $type = self::getMimeFromExtension($ext);
            if ($type)
                return array($ext, $type);
        }

        return mime_content_type($path);
    }

    public static function getExtension(string $mime)
    {
        $mime = strtolower($mime);
        $refl = new ReflectionClass(static::class);
        $extensions = $refl->getConstants();

        $ext = array_search($mime, $extensions, $strict);
        return $ext === false ? null : $ext;
    }


}
