<?php

class DBReadTest extends \PHPUnit\Framework\TestCase {

  private static $dir = 'test-database';
  private $w;

  public function setUp(): void {
    shell_exec('rm -rf '.self::$dir);
    mkdir(self::$dir);
    $this->w = new Quartz\DB(self::$dir, 'w');
  }

  public function testIteratesEveryRow() {
    for($i=1; $i<4; $i++) {
      $data = ['line' => $i];
      $line = $this->w->add(new DateTime('2015-08-07 09:00:00.00000'.$i.' -0000'), $data);
    }
    // Make sure the test file was written properly
    $lines = file(self::$dir.'/2015/08/07.txt', FILE_IGNORE_NEW_LINES);
    $this->assertEquals('2015-08-07 09:00:00.000001 {"line":1}', $lines[0]);
    $this->assertEquals('2015-08-07 09:00:00.000003 {"line":3}', $lines[2]);

    $db = new Quartz\DB(self::$dir, 'r');
    $results = $db->queryRange('2015-08-07T00:00:00', '2015-08-07T23:59:59');
    $num = 0;
    foreach($results as $r) {
      $num++;
      $this->assertEquals('{"line":'.$num.'}', json_encode($r->data));
    }
    $this->assertEquals(3, $num);
  }

  public function testReadLatestRecordsFromMany() {
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

    $results = $db->queryLast(2);
    $num = 7;
    foreach($results as $r) {
      $num++;
      $this->assertEquals('{"line":'.$num.'}', json_encode($r->data));
    }
    $this->assertEquals(9, $num);
  }

  public function testIteratesAcrossShards() {
    for($i=1; $i<5; $i++) {
      $data = ['line' => $i];
      $line = $this->w->add(new DateTime('2015-08-07 23:59:00.00000'.$i.' -0000'), $data);
    }
    for($i=5; $i<10; $i++) {
      $data = ['line' => $i];
      $line = $this->w->add(new DateTime('2015-08-08 01:01:00.00000'.$i.' -0000'), $data);
    }
    $lines = file(self::$dir.'/2015/08/07.txt', FILE_IGNORE_NEW_LINES);
    $this->assertEquals('2015-08-07 23:59:00.000001 {"line":1}', $lines[0]);
    $this->assertEquals('2015-08-07 23:59:00.000004 {"line":4}', $lines[3]);
    $lines = file(self::$dir.'/2015/08/08.txt', FILE_IGNORE_NEW_LINES);
    $this->assertEquals('2015-08-08 01:01:00.000005 {"line":5}', $lines[0]);
    $this->assertEquals('2015-08-08 01:01:00.000009 {"line":9}', $lines[4]);

    $db = new Quartz\DB(self::$dir, 'r');
    $results = $db->queryRange('2015-08-07T00:00:00', '2015-08-08T23:59:59');
    $num = 0;
    foreach($results as $r) {
      $num++;
      $this->assertEquals('{"line":'.$num.'}', json_encode($r->data));
    }
    $this->assertEquals(9, $num);
  }

}
