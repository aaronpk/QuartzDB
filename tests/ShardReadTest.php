<?php

// Reading shards line by line: record IDs, lookups by ID and by date, the last
// n records, and files that have blank lines or no final line ending.
class ShardReadTest extends \PHPUnit\Framework\TestCase {

  private static $dir = 'test-database';
  private static $file = 'test-database/2015/08/07.txt';

  public function setUp(): void {
    shell_exec('rm -rf '.self::$dir);
    mkdir(self::$dir);
    $w = new Quartz\DB(self::$dir, 'w');
    for($i=1; $i<=5; $i++) {
      $w->add(new DateTime('2015-08-07 09:00:0'.$i.' -0000'), ['line' => $i]);
    }
    $w->close();
  }

  private function db() {
    return new Quartz\DB(self::$dir, 'r');
  }

  private function shard() {
    $shard = $this->db()->shardForDate(new DateTime('2015-08-07'));
    $shard->init();
    return $shard;
  }

  // Rewrites the shard file with a blank line inserted before the given line
  private function insertBlankLine($before, $blank='') {
    $lines = file(self::$file, FILE_IGNORE_NEW_LINES);
    array_splice($lines, $before, 0, [$blank]);
    file_put_contents(self::$file, implode("\n", $lines)."\n");
  }

  private function removeFinalNewline() {
    file_put_contents(self::$file, rtrim(file_get_contents(self::$file), "\n"));
  }

  // [record ID => line value] for everything the iterator returns
  private function rows($iterator) {
    $rows = [];
    foreach($iterator as $key => $record) {
      $this->assertNotNull($record, 'The iterator returned an empty record for key '.$key);
      $this->assertSame((string)$key, (string)$record->id);
      $rows[$record->id] = $record->data->line;
    }
    return $rows;
  }

  public function testRecordIDsAreTheDateAndZeroBasedLineNumber() {
    $this->assertSame([
      '201508070' => 1, '201508071' => 2, '201508072' => 3, '201508073' => 4, '201508074' => 5
    ], $this->rows($this->shard()));
  }

  public function testAShardCanBeIteratedTwice() {
    $shard = $this->shard();
    $first = $this->rows($shard);
    $this->assertCount(5, $first);
    $this->assertSame($first, $this->rows($shard));
  }

  public function testGetByIDReturnsTheLineTheIteratorGaveThatID() {
    foreach($this->rows($this->shard()) as $id => $line) {
      $record = $this->db()->getByID($id);
      $this->assertSame($line, $record->data->line);
      $this->assertSame((string)$id, (string)$record->id);
    }
  }

  public function testGetByIDPastTheEndReturnsNull() {
    $this->assertNull($this->db()->getByID('201508075'));
    $this->assertNull($this->db()->getByID('2015080799'));
  }

  public function testGetByIDInAMissingShardReturnsNull() {
    $this->assertNull($this->db()->getByID('201508090'));
  }

  public function testGetByDateFindsTheFirstMiddleAndLastLine() {
    foreach([1 => '201508070', 3 => '201508072', 5 => '201508074'] as $line => $id) {
      $record = $this->db()->getByDate('2015-08-07T09:00:0'.$line);
      $this->assertNotNull($record, 'No record found for line '.$line);
      $this->assertSame($line, $record->data->line);
      $this->assertSame($id, (string)$record->id);
    }
  }

  public function testGetByDateReturnsNullWhenNothingMatches() {
    $this->assertNull($this->db()->getByDate('2015-08-07T08:00:00'));
    $this->assertNull($this->db()->getByDate('2015-08-07T09:00:07'));
  }

  public function testGetByDateSkipsBlankLines() {
    $this->insertBlankLine(0);
    $this->insertBlankLine(3, '  ');
    $record = $this->db()->getByDate('2015-08-07T09:00:01');
    $this->assertSame(1, $record->data->line);
    $this->assertSame('201508071', (string)$record->id);
    $record = $this->db()->getByDate('2015-08-07T09:00:05');
    $this->assertSame(5, $record->data->line);
    $this->assertSame('201508076', (string)$record->id);
    $this->assertNull($this->db()->getByDate('2015-08-07T09:00:07'));
  }

  public function testBlankLinesKeepTheirLineNumber() {
    $this->insertBlankLine(2);
    $this->assertSame([
      '201508070' => 1, '201508071' => 2, '201508073' => 3, '201508074' => 4, '201508075' => 5
    ], $this->rows($this->shard()));
    $this->assertNull($this->db()->getByID('201508072'));
    $this->assertSame(3, $this->db()->getByID('201508073')->data->line);
  }

  public function testQueryLastReturnsTheLastRecords() {
    $this->assertSame(['201508074' => 5], $this->rows($this->db()->queryLast(1)));
    $this->assertSame(['201508072' => 3, '201508073' => 4, '201508074' => 5], $this->rows($this->db()->queryLast(3)));
    $this->assertCount(5, $this->rows($this->db()->queryLast(5)));
    $this->assertCount(5, $this->rows($this->db()->queryLast(100)));
  }

  public function testQueryLastNeverReturnsEmptyRecordsForBlankLines() {
    file_put_contents(self::$file, "\n\n", FILE_APPEND);
    $this->assertSame(['201508073' => 4, '201508074' => 5], $this->rows($this->db()->queryLast(2)));
    $this->insertBlankLine(4, ' ');
    $this->assertSame(['201508073' => 4, '201508075' => 5], $this->rows($this->db()->queryLast(2)));
  }

  public function testRangeQueryIncludesAnUnterminatedLastLine() {
    $this->removeFinalNewline();
    $this->assertCount(5, $this->rows($this->db()->queryRange('2015-08-07T00:00:00', '2015-08-07T23:59:59')));
    $this->assertSame(['201508074' => 5], $this->rows($this->db()->queryRange('2015-08-07T09:00:05', '2015-08-07T09:00:09')));
  }

  public function testRangeQueryEndIsExclusive() {
    $this->assertSame(['201508071' => 2, '201508072' => 3],
      $this->rows($this->db()->queryRange('2015-08-07T09:00:02', '2015-08-07T09:00:04')));
  }

  public function testRangeQueryCanBeIteratedTwice() {
    $results = $this->db()->queryRange('2015-08-07T09:00:02', '2015-08-07T23:00:00');
    $first = $this->rows($results);
    $this->assertCount(4, $first);
    $this->assertSame($first, $this->rows($results));
  }

  public function testAppendingAfterAnUnterminatedLineStartsANewLine() {
    $this->removeFinalNewline();
    $w = new Quartz\DB(self::$dir, 'w');
    $w->add(new DateTime('2015-08-07 09:00:09 -0000'), ['line' => 9]);
    $w->close();
    $lines = file(self::$file, FILE_IGNORE_NEW_LINES);
    $this->assertCount(6, $lines);
    $this->assertSame('2015-08-07 09:00:05.000000 {"line":5}', $lines[4]);
    $this->assertSame('2015-08-07 09:00:09.000000 {"line":9}', $lines[5]);
    $this->assertSame(['201508074' => 5, '201508075' => 9], $this->rows($this->db()->queryLast(2)));
  }

  public function testAnEmptyShardFileHasNoRecords() {
    file_put_contents(self::$file, '');
    $this->assertSame([], $this->rows($this->shard()));
    $this->assertSame([], $this->rows($this->db()->queryRange('2015-08-07T00:00:00', '2015-08-07T23:59:59')));
    $this->assertNull($this->db()->getByID('201508070'));
    $this->assertNull($this->db()->getByDate('2015-08-07T09:00:01'));
  }

  public function testCountAndMetaFileTrackAddedRecords() {
    $this->assertSame(5, $this->shard()->count());
    $w = new Quartz\DB(self::$dir, 'w');
    $this->assertSame(6, $w->add(new DateTime('2015-08-07 09:00:06 -0000'), ['line' => 6]));
    $this->assertSame(7, $w->add(new DateTime('2015-08-07 09:00:07 -0000'), ['line' => 7]));
    $w->close();
    $this->assertSame("7\n", file_get_contents(self::$dir.'/2015/08/07.meta'));
    $this->assertSame(7, $this->shard()->count());
  }

}
