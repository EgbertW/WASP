<?php

namespace WASP;

use PHPUnit\Framework\TestCase;

/**
 * @covers WASP\Dictionary
 */
final class DictionaryTest extends TestCase
{
    function testConstruct()
    {
        $dict = new Dictionary();
        $this->assertInstanceOf(Dictionary::class, $dict);
        $this->assertTrue(empty($dict->getAll()));
    }

    function testConstructArray()
    {
        $data = array('var1' => 'val1', 'var2' => 'val2');
        $dict = new Dictionary($data);

        $this->assertEquals($dict['var1'], 'val1');
        $this->assertEquals($dict['var2'], 'val2');
        $this->assertEquals($dict->get('var1'), 'val1');
        $this->assertEquals($dict->get('var2'), 'val2');
        $this->assertEquals($dict->get('var3'), null);
        $this->assertEquals($dict->dget('var3', 'foo'), 'foo');

        // Set the var3 value to check setting
        $dict->set('var3', 'val3');
        $this->assertEquals($dict->dget('var3', 'foo'), 'val3');

        // Test if referenced array is updated
        $this->assertEquals($data['var3'], 'val3');
    }

    function testConstructArrayRecursive()
    {
        $data = array('var1' => 'val1', 'var2' => array('a' => 1, 'b' => 2, 'c' => 3));
        $dict = new Dictionary($data);

        $this->assertEquals($dict['var1'], 'val1');
        $this->assertTrue($dict->has('var2', Dictionary::TYPE_ARRAY));
        $this->assertEquals($dict->get('var2', 'a'), 1);
        $this->assertEquals($dict->get('var2', 'b'), 2);
        $this->assertEquals($dict->get('var2', 'c'), 3);
        $this->assertNull($dict->get('var2', 'd'));

        $dict->set('var2', 'd', 4);
        $this->assertTrue($dict->has('var2', 'd', Dictionary::TYPE_INT));

        // Test if referenced array is updated
        $this->assertEquals($data['var2']['d'], 4);
    }

    function testTypeChecking()
    {
        $dict = new Dictionary();

        $dict->set('int', 1);
        $dict->set('float', 1.0);
        $dict->set('object', new \StdClass());
        $dict->set('array', array(1, 2, 3));
        $dict->set('string', 'test');
        $dict->set('stringint', '1');
        $dict->set('stringfloat', '1.0');

        $this->assertTrue($dict->has('int'));
        $this->assertTrue($dict->has('int', Dictionary::TYPE_INT));
        $this->assertTrue($dict->has('int', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('int', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('float'));
        $this->assertTrue($dict->has('float', Dictionary::TYPE_FLOAT));
        $this->assertTrue($dict->has('float', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('float', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('object'));
        $this->assertTrue($dict->has('object', Dictionary::TYPE_OBJECT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('object', Dictionary::TYPE_ARRAY));

        $this->assertTrue($dict->has('array'));
        $this->assertTrue($dict->has('array', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('array', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('string'));
        $this->assertTrue($dict->has('string', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_NUMERIC));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('string', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('stringint'));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_NUMERIC));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_INT));
        $this->assertTrue($dict->has('stringint', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringint', Dictionary::TYPE_OBJECT));

        $this->assertTrue($dict->has('stringfloat'));
        $this->assertTrue($dict->has('stringfloat', Dictionary::TYPE_NUMERIC));
        $this->assertTrue($dict->has('stringfloat', Dictionary::TYPE_STRING));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_ARRAY));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_INT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_FLOAT));
        $this->assertFalse($dict->has('stringfloat', Dictionary::TYPE_OBJECT));
    }
}
