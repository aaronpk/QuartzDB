<?php

class DBReadTest extends PHPUnit_Framework_TestCase {

  private static $dir = 'test-database';
  private $w;

  public function setUp() {
    shell_exec('rm -rf '.self::$dir);
    mkdir(self::$dir);
    $this->w = new Quartz\DB(self::$dir, 'w');
  }

  public function testReadLatestRecordFromMany() {
    for($i=1; $i<10; $i++) {
      $data = ['line' => $i];
      $line = $this->w->add(new DateTime('2015-08-07 09:00:00.00000'.$i.' -0000'), $data);
    }
    $lines = file(self::$dir.'/2015/08/07.txt', FILE_IGNORE_NEW_LINES);
    $this->assertEquals('2015-08-07 09:00:00.000001 {"line":1}', $lines[0]);
    $this->assertEquals('2015-08-07 09:00:00.000009 {"line":9}', $lines[8]);

    $db = new Quartz\DB(self::$dir, 'r');
    $results = $db->queryLast(1);
    $num = 0;
    foreach($results as $r) {
      $num++;
      $this->assertEquals('{"line":9}', json_encode($r->data));
    }
    $this->assertEquals(1, $num);
  }

}
