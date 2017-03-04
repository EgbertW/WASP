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

namespace WASP\DB\Query;

use PHPUnit\Framework\TestCase;

class ConstantExpressionTest extends TestCase
{
    public function testIntConstant()
    {
        $mock = $this->prophesize(Parameters::class);
        $mock->assign(5)->willReturn('foo');
        $p = $mock->reveal();

        $a = new ConstantExpression(5);
        $sql = $a->toSQL($p, false);
        $this->assertEquals(":foo", $sql);
        $this->assertFalse($a->isNull());
    }

    public function testStringConstant()
    {
        $mock = $this->prophesize(Parameters::class);
        $mock->assign("bar")->willReturn('baz');
        $p = $mock->reveal();

        $a = new ConstantExpression("bar");
        $sql = $a->toSQL($p, false);
        $this->assertEquals(":baz", $sql);
        $this->assertFalse($a->isNull());
    }

    public function testFloatConstant()
    {
        $mock = $this->prophesize(Parameters::class);
        $mock->assign(3.5)->willReturn('foobar');
        $p = $mock->reveal();

        $a = new ConstantExpression(3.5);
        $sql = $a->toSQL($p, false);
        $this->assertEquals(":foobar", $sql);
        $this->assertFalse($a->isNull());
    }

    public function testNullConstant()
    {
        $mock = $this->prophesize(Parameters::class);
        $mock->assign(null)->shouldNotBeCalled();
        $p = $mock->reveal();

        $a = new ConstantExpression(null);
        $sql = $a->toSQL($p, false);
        $this->assertEquals("NULL", $sql);
        $this->assertTrue($a->isNull());
    }

    public function testRegisterTablesDoesNothing()
    {
        $mock = $this->prophesize(Parameters::class);
        $mock->registerTable()->shouldNotBeCalled();
        $p = $mock->reveal();

        $a = new ConstantExpression(null);
        $sql = $a->registerTables($p);
    }
}
