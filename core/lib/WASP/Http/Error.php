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

use WASP\is_array_like;
use WASP\Template;
use WASP\Dictionary;
use WASP\Debug\Logger;
use WASP\Debug\LoggerAwareStaticTrait;

use Throwable;

class Error extends Response
{
    use LoggerAwareStaticTrait;

    private static $nesting_counter = 0;
    private $user_message;

    public function __construct($code, $error, $user_message = null, $previous = null)
    {
        parent::__construct($error, $code, $previous);
        $this->user_message = $user_message;
    }

    public function getUserMessage()
    {
        return $this->user_message;
    }

    public function getMimeTypes()
    {
        return array_keys(DataResponse::$representation_types);
    }

    public function output(string $mime)
    {
        if ($mime === "text/html" || $mime === "text/plain")
            $this->toStringResponse($mime)->output($mime);
        else
            $this->toDataResponse($mime)->output($mime);
    }

    public function transformResponse(string $mime)
    {
        if ($mime === "text/html" || $mime === "text/plain")
            return $this->toStringResponse($mime);

        return $this->toDataResponse($mime);
    }

    private function toStringResponse(string $mime)
    {
        $exception = $this->getPrevious();
        if ($exception === null)
            $exception = $this;

        try
        {
            $template = $this->getRequest()->getTemplate();
            $template->setExceptionTemplate($exception);
            $template->assign('exception', $exception);
            $template->render();
        }
        catch (Response $e)
        {
            return $e;
        }
        catch (Throwable $e)
        {
            self::$logger->emergency("Could not render error template, using fallback writer! Exception: {0}", [$e]);
            $op = array('exception' => $exception);

            // Write the output and return it as a string response
            ob_start();
            self::fallbackWriter($op, $mime);
            $op = ob_get_contents();
            ob_end_clean();

            return new StringResponse($op, $mime);
        }
    }

    private function toDataResponse(string $mime)
    {
        $status = $this->getStatusCode();
        $msg = isset(StatusCode::$CODES[$status]) ? StatusCode::$CODES[$status] : "Internal Server Error";
        $data = array(
            'message' => $msg,
            'status_code' => $this->getStatusCode(),
            'exception' => $this,
            'status' => $this->getStatusCode()
        );

        $dict = new Dictionary($data);
        return new DataResponse($dict);
    }

    public static function fallbackWriter($output, $mime = "text/plain")
    {
        $html = $mime === 'text/html';
        if ($html)
            echo "<!doctype html><html><head><title>Error</title></head><body>";

        self::outputPlainText($output, 0, $html);

        if ($html)
            echo "</body></html>\n";

    }

    public static function outputPlainText($data, int $indent, bool $html)
    {
        if (!\WASP\is_array_like($data))
        {
            printf("%s\n", Logger::str($data, $html));
            return;
        }

        if ($html)
        {
            $indentstr = str_repeat('&nbsp;', $indent);
            $nl = "<br>\n";
        }
        else 
        {
            $indentstr = str_repeat(' ', $indent);
            $nl = "\n";
        }
        foreach ($data as $key => $value)
        {
            if (\WASP\is_array_like($value))
            {
                printf("%s%s = {%s", $indentstr, $key, $nl);
                self::outputPlainText($value, $indent + 4, $html);
                printf("%s}%s", $indentstr, $nl);
            }
            else
            {
                printf("%s%s = %s%s", str_repeat(' ', $indent), $key, Logger::str($value, $html), $nl);
            }
        }
    }
}

// @codeCoverageIgnoreStart
Error::setLogger();
// @codeCoverageIgnoreEnd
