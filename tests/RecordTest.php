<?php

namespace Medlib\MarcXML\Testing;

use Medlib\MarcXML\Records\Record;

class RecordTest extends AbstractTestCase
{
    public function testIsset()
    {
        $rec = new Record;
        $rec->key = 'value';
        $this->assertFalse(isset($rec->someRandomStuff));
        $this->assertTrue(isset($rec->key));
        $this->assertEquals('value', $rec->key);
    }

    public function testSerializations()
    {
        $rec = new Record;
        $rec->key = 'value';
        $this->assertEquals(['key' => 'value'], $rec->toArray());
        $this->assertJsonStringEqualsJsonString(json_encode(['key' => 'value']), $rec->toJson());
    }

    public function testMagicMethods()
    {
        $rec = new Record;
        $rec->lalala = 'humdidum';
        $this->assertTrue(isset($rec->lalala));
        $this->assertFalse(isset($rec->humdidum));
        unset($rec->lalala);
        $this->assertFalse(isset($rec->lalala));
    }
}