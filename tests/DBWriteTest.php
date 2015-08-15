<?php

class DBWriteTest extends PHPUnit_Framework_TestCase {

  private static $dir = 'test-database';
  private $db;

  public function setUp() {
    shell_exec('rm -rf '.self::$dir);
    mkdir(self::$dir);
    $this->db = new Quartz\DB(self::$dir, 'w');
  }

  public function testAddOneRecord() {

    // Add the record, which should return the new number of lines in the new file
    $data = ['line' => 1];
    $line = $this->db->add(new DateTime('2015-07-07 09:00:00.000001 -0000'), $data);
    $this->assertEquals(1, $line);

    // Check that the data file has the right contents
    $this->assertEquals('2015-07-07 09:00:00.000001 {"line":1}', file_get_contents(self::$dir.'/2015/07/07.txt'));

    // Check that the meta file has the right contents
    $this->assertEquals('1', trim(file_get_contents(self::$dir.'/2015/07/07.meta')));

    // Check that the "lastshard.txt" file has the right contents
    $this->assertEquals('2015-07-07', file_get_contents(self::$dir.'/lastshard.txt'));
  }

  public function testIncrementsLastShard() {
    $data = ['line' => 1, 'shard' => 1];
    $line = $this->db->add(new DateTime('2015-07-07 09:00:00.000001 -0000'), $data);
    $this->assertEquals(1, $line);

    $data = ['line' => 1, 'shard' => 2];
    $line = $this->db->add(new DateTime('2015-07-08 09:00:00.000001 -0000'), $data);
    $this->assertEquals(1, $line);

    $this->assertEquals('2015-07-08', file_get_contents(self::$dir.'/lastshard.txt'));    
  }

  public function testAddsLineToShard() {
    $data = ['line' => 1, 'shard' => 1];
    $line = $this->db->add(new DateTime('2015-07-07 09:00:00.000001 -0000'), $data);
    $this->assertEquals(1, $line);

    $data = ['line' => 2, 'shard' => 1];
    $line = $this->db->add(new DateTime('2015-07-07 09:00:00.000002 -0000'), $data);
    $this->assertEquals(2, $line);

    // Check that the last shard is still correct
    $this->assertEquals('2015-07-07', file_get_contents(self::$dir.'/lastshard.txt'));    

    // Check that the meta file says there are two records
    $this->assertEquals('2', trim(file_get_contents(self::$dir.'/2015/07/07.meta')));

    // Check that the file contains two lines
    $this->assertEquals(2, count(file(self::$dir.'/2015/07/07.txt')));
  }

}
