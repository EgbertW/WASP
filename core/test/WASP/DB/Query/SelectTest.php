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

use WASP\DB\Query\Builder as Q;

/**
 * @covers WASP\DB\Query\Select
 */
class SelectTest extends TestCase
{
    public function testSelect()
    {
        $q = Q::select(
            Q::field('barbaz'),
            Q::alias('foobar', 'baz'),
            Q::from('test', 't1'),
            Q::where(
                Q::equals(
                    'foo',
                    3
                )
            ),
            Q::join(
                Q::with('test2', 't2'),
                Q::on(
                    Q::equals(
                        Q::field('id', 't2'),
                        Q::field('id', 't1')
                    )
                )
            ),
            Q::order('foo'),
            Q::limit(10),
            Q::offset(5)
        );

        $this->assertInstanceOf(Select::class, $q);

        $wh = $q->getWhere();
        $this->assertInstanceOf(WhereClause::class, $wh);

        $joins = $q->getJoins();
        $this->assertEquals(1, count($joins));
        $this->assertInstanceOf(JoinClause::class, $joins[0]);

        $t = $q->getTable();
        $this->assertInstanceOf(SourceTableClause::class, $t);

        $fields = $q->getFields();
        $this->assertEquals(2, count($fields));
        $this->assertInstanceOf(FieldAlias::class, $fields[0]);
        $this->assertInstanceOf(FieldAlias::class, $fields[1]);

        $this->assertInstanceOf(LimitClause::class, $q->getLimit());
        $this->assertInstanceOf(OffsetClause::class, $q->getOffset());
        $this->assertInstanceOf(OrderClause::class, $q->getOrder());
    }

    public function testDeleteQueryWithObjects()
    {
        $table = new TableClause("test_table");
        $op = new ComparisonOperator("=", "foo", new FieldName("bar"));

        $where = new WhereClause($op);

        $d = new Delete($table, $where);
        $t = $d->getTable();
        $this->assertInstanceOf(TableClause::class, $t);
        $this->assertEquals("test_table", $t->getTable());

        $w = $d->getWhere();
        $this->assertInstanceOf(WhereClause::class, $w);

        $op = $w->getOperand();
        $this->assertInstanceOf(ComparisonOperator::class, $op);
        $this->assertEquals("=", $op->getOperator());
    }

    public function testInvalidTable()
    {
        $table = new \StdClass;
        $where = "foo = bar";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid table");
        $d = new Delete($table, $where);
    }
}
