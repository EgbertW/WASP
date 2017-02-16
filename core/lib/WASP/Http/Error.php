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

use Template;

class Error extends Response
{
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

    public function getMime()
    {
        return array_keys(DataResponse::$representation_types);
    }

    public function output(string $mime)
    {
        // @codeCoverageIgnoreStart
        // If this executes, there's debugging to do
        if (self::$nesting_counter++ > 5)
            die("Too much nesting in error output - probably a bug");
        // @codeCoverageIgnoreEnd

        if ($mime === "text/html")
            return $this->outputTemplate();
        
        $status = $this->getStatusCode();
        $msg = isset(StatusCode::$CODES[$status]) ? StatusCode::$CODES[$status] = "Internal Server Error";
        $data = array(
            'message' => $msg,
            'status_code' => $this->getStatusCode(),
            'exception' => $this,
            'cause' => $this->getPrevious(),
            'status' => $this->getStatusCode(),
        );
        $data = new Dictionary($data);

        $wrapped = new Http\DataResponse($data);
        $wrapped->output($mime);
    }

    protected function outputTemplate()
    {
        $exception = $this->getPrevious();
        if ($exception === null)
            $exception = $this;

        $template = new Template(Template::findExceptionTemplate($exception));
        $template->assign('exception', $exception);

        try
        {
            $template->render();
        }
        catch (Response $e)
        {
            $e->output();
        }
    }
}
