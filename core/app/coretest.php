<?php

use WASP\Http\Request;
use WASP\Http\StringResponse;

class MyClass
{
    public function edit(Request $req, int $arg)
    {
        throw new StringResponse("Halloha " . $arg, "text/html");
    }
}

return new MyClass();
