<?php

class DBWriteTest extends PHPUnit_Framework_TestCase {

  private static $dir = 'test-database';

  public function setUp() {
    shell_exec('rm -rf '.self::$dir);
    mkdir(self::$dir);
  }

  public function testAddOneRecord() {
    $db = new Quartz\DB(self::$dir, 'w');
    $data = ['line' => 1];

    // Add the record, which should return the new number of lines in the new file
    $line = $db->add(new DateTime('2015-07-07 09:00:00.000001 -0700'), $data);
    $this->assertEquals(1, $line);

    // Check that the data file has the right contents
    $this->assertEquals('2015-07-07 16:00:00.000001 {"line":1}', file_get_contents(self::$dir.'/2015/07/07.txt'));

    // Check that the meta file has the right contents
    $this->assertEquals('1', trim(file_get_contents(self::$dir.'/2015/07/07.meta')));

    // Check that the "lastshard.txt" file has the right contents
    $this->assertEquals('2015-07-07', file_get_contents(self::$dir.'/lastshard.txt'));
  }

}
