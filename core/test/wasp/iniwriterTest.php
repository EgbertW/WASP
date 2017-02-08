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
 * @covers WASP\INIWriter
 */
class INIWriterTest extends TestCase
{
    /**
     * @covers WASP\INIWriter::write
     * @covers WASP\INIWriter::writeParameter
     */
    public function testIniWriter()
    {
        $ini = <<<EOT
;precomment about this file
[sec1]
;a-comment for section 1
;testcomment for section 1
var1 = "value1"
var2 = "value2"

[sec2]
;z-comment for section 2
;testcomment for section 2
var3 = "value3"
var4 = "value4"
EOT;
        $ini_expected = <<<EOT
;precomment about this file

[sec1]
;a-comment for section 1
;testcomment for section 1
var1 = "value1"
var2 = "value2"

[sec2]
;testcomment for section 2
;z-comment for section 2
var3 = "value3"
var4 = "value4"

[sec3]
var5 = "value5"

EOT;
        $dir = WASP_ROOT . '/test';
        if (!is_dir($dir))
            mkdir($dir);

        $file = $dir . '/test.ini';
        file_put_contents($file, $ini);

        // Read contents
        $cfg = parse_ini_file($file, true, INI_SCANNER_TYPED);
        $this->assertEquals($cfg['sec1']['var1'], 'value1');
        $this->assertEquals($cfg['sec1']['var2'], 'value2');
        $this->assertEquals($cfg['sec2']['var3'], 'value3');
        $this->assertEquals($cfg['sec2']['var4'], 'value4');
        
        $cfg['sec3']['var5'] = 'value5';
        INIWriter::write($file, $cfg);

        $ini_out = file_get_contents($file);
        $this->assertEquals($ini_out, $ini_expected);

        unlink($file);
        rmdir($dir);
    }
}
