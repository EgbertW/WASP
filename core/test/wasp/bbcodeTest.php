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
 * @covers WASP\BBCode
 */
final class bbcodeTest extends TestCase
{
    /**
     * @covers WASP\BBCode::__construct
     * @covers WASP\BBCode::setDefault
     * @covers WASP\BBCode::getDefault
     */
    public function testDefault()
    {
        $a = new BBCode();

        BBCode::setDefault($a);
        
        $b = BBCode::getDefault();
        $this->assertTrue($a === $b);
    }
    
    /**
     * @covers WASP\BBCode::__construct
     * @covers WASP\BBCode::apply
     */
    public function testConfig()
    {
        $replc = array(
            'foo/bar' => 'foo',
            '/[0-9]+/' => '*'
        );

        $a = new BBCode($replc);
        
        $str = 'The foo/bar has 3 to 5 versions';
        $nstr = $a->apply($str);

        $this->assertEquals(
            'The foo has * to * versions',
            $nstr
        );

        // Try with different constructor, config like
        $replc = new Dictionary();
        $replc['patterns'] = array(
            'foo/bar',
            '/[0-9]+/'
        );

        $replc['replacements'] = array(
            'foo',
            '*'
        );

        $a = new BBCode($replc);
        $nstr = $a->apply($str);

        $this->assertEquals(
            'The foo has * to * versions',
            $nstr
        );
    }

    /**
     * @covers WASP\BBCode::__construct
     * @covers WASP\BBCode::addRule
     * @covers WASP\BBCode::apply
     */
    public function testAddRule()
    {
        $replc = array(
            'foo/bar' => 'foo',
            '/[0-9]+/' => '*'
        );

        $a = new BBCode();

        foreach ($replc as $rule => $repl)
            $a->addRule($rule, $repl);
        
        $str = 'The foo/bar has 3 to 5 versions';
        $nstr = $a->apply($str);

        $this->assertEquals(
            'The foo has * to * versions',
            $nstr
        );
    }
}
