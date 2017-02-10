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

use PHPUnit\Framework\TestCase;

/**
 * @covers WASP\JSON
 */
final class JSONTest extends TestCase
{
    /**
     * @covers WASP\JSON::init
     * @covers WASP\JSON::add
     * @covers WASP\JSON::get
     * @covers WASP\JSON::remove
     * @covers WASP\JSON::setPrettyPrinting
     * @covers WASP\JSON::setCallback
     */
    public function testJSON()
    {
        $accept = array(
            'application/json' => 1.0,
            '*/*' => 0.5
        );

        $this->assertNotEquals(Request::$accept, $accept);
        JSON::init();
        $this->assertEquals(Request::$accept, $accept);
        JSON::clear();
        
        JSON::add('var1', 'val1');
        JSON::add('var2', 'val2', 'var3', 'val3');
        JSON::add(array('var4' => 'val4', 'var5' => 'val5'));

        $this->assertEquals(JSON::get('var1'), 'val1');
        $this->assertEquals(JSON::get('var2'), 'val2');
        $this->assertEquals(JSON::get('var3'), 'val3');
        $this->assertEquals(JSON::get('var4'), 'val4');
        $this->assertEquals(JSON::get('var5'), 'val5');
        $this->assertEquals(JSON::get('var6'), null);

        JSON::remove('var3');
        $this->assertEquals(JSON::get('var3'), null);


        $data = array(
            'a' => 1,
            'b' => true,
            'c' => "test",
            'd' => array(
                'e' => 2,
                'f' => false,
                'g' => 5.5
            ),
            'e' => null,
            99 => 'value'
        );

        $json = JSON::UTF8SafeEncode($data);
        $this->assertEquals($json, json_encode($data));

        $brokendata = array(
            'a' => "\x00\x80\xc2\x9e\xe2\x80\xa0" . 'example'
        );
        $json = JSON::UTF8SafeEncode($brokendata);
        $json2 = json_encode($brokendata);
        
        $this->assertFalse($json2);
        $this->assertEquals(json_last_error(), JSON_ERROR_UTF8);
        $this->assertEquals($json, '{"a":"\u0000???example"}');

        $this->assertTrue(JSON::setPrettyPrinting(true));
        $this->assertTrue(JSON::setPrettyPrinting(1));
        $this->assertTrue(JSON::setPrettyPrinting("test"));
        $this->assertFalse(JSON::setPrettyPrinting(false));
        $this->assertFalse(JSON::setPrettyPrinting(0));
        $this->assertFalse(JSON::setPrettyPrinting(null));

        $this->assertEquals(JSON::setCallback("foobar"), "foobar");

        $json = JSON::pprint($data);
        $expected_json = <<<EOT
{
    "a": 1,
    "b": true,
    "c": "test",
    "d": {
        "e": 2,
        "f": false,
        "g": 5.5
    },
    "e": null,
    "99": "value"
}
EOT;
        $this->assertEquals($json, $expected_json);
    }

    /** 
     * @covers WASP\JSON::pprint
     */
    public function testSerializable()
    {
        $dict = new Dictionary(); // A JsonSerializable class
        $dict['test'] = 1;

        $json = JSON::pprint($dict);
        $this->assertEquals($json, "{\n    \"test\": 1\n}");
    }

    /** 
     * @covers WASP\JSON::pprint
     */
    public function testSerializableException()
    {
        $obj = new \StdClass;
        $obj->test = 1;

        $this->expectException(\RuntimeException::class);
        $json = JSON::pprint($obj);
    }
}
