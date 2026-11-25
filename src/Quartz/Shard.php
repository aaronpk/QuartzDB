<?php
namespace Quartz;

use DateTime, DateTimeZone;

class Shard implements \Iterator {

  private $_db;
  private $_path;
  private $_filename;
  private $_metaFilename;
  private $_fp = null;
  private $_mp = null;

  private $_fileWasJustCreated = false;

  private $_y;
  private $_m;
  private $_d;
  private $_date;

  private $_queryFrom;
  private $_queryTo;
  private $_queryFromLine;

  // The data file is read with plain fgets() and the line number is counted
  // here. SplFileObject's seek(), key() and next() have behaved differently
  // from one PHP version to the next (php/php-src#8561).
  // Zero-based number of the current line
  private $_line = 0;
  // The current line without its line ending. null: not read yet, false: end of file
  private $_buf = null;
  // Whether the current line is blank, or the end of the file was reached
  private $_blank = true;

  use Helpers;

  public function __construct($db, $y, $m, $d) {
    $this->_db = $db;
    // get the path for this shard
    $this->_path = $db->path . '/' . $y . '/' . $m . '/';
    // get the filename for this shard
    $this->_filename = $this->_path . $d . '.txt';
    $this->_metaFilename = $this->_path . $d . '.meta';

    $this->_y = $y;
    $this->_m = $m;
    $this->_d = $d;

    $this->_date = new DateTime("$y-$m-$d", new DateTimeZone('UTC'));
  }

  public static function createFromDate($db, DateTime $date) {
    $y = $date->format('Y');
    $m = $date->format('m');
    $d = $date->format('d');
    return new Shard($db, $y, $m, $d);
  }

  public function exists() {
    return file_exists($this->_filename);
  }

  public function init() {
    // create the folder if it doesn't exist
    if($this->_db->mode == 'w' && !file_exists($this->_path)) {
      mkdir($this->_path, 0755, true);
    }

    // create the file if it doesn't exist when in "write" mode
    if($this->_db->mode == 'w' && !$this->exists()) {
      touch($this->_filename);
      touch($this->_metaFilename);
      $this->_fileWasJustCreated = true;
      // If this shard is a later date than the last known shard, update that file
      if(file_exists($this->_db->lastShardFile())) {
        $last = new DateTime(file_get_contents($this->_db->lastShardFile()), new DateTimeZone('UTC'));
        if($this->_date > $last) {
          $updateLastShard = $this->_date;
        } else {
          $updateLastShard = false;
        }
      } else {
        $updateLastShard = $this->_date;
      }
      if($updateLastShard) {
        file_put_contents($this->_db->lastShardFile(), $updateLastShard->format('Y-m-d'));
      }
    }

    // open the file pointer
    if($this->exists()) {
      $this->close();
      if($this->_db->mode == 'w') {
        // A last line that was cut off mid-write has no line ending. Finish it,
        // so the next record doesn't get appended to the same line.
        $existing = fopen($this->_filename, 'r');
        if(fstat($existing)['size'] > 0) {
          fseek($existing, -1, SEEK_END);
          $missingNewline = fgetc($existing) !== "\n";
        } else {
          $missingNewline = false;
        }
        fclose($existing);
        $this->_fp = fopen($this->_filename, 'a');
        if($missingNewline)
          fwrite($this->_fp, "\n");
        $this->_mp = fopen($this->_metaFilename, 'r+');
        if($this->_fileWasJustCreated) {
          fwrite($this->_mp, "0\n");
        }
      } else {
        $this->_fp = fopen($this->_filename, 'r');
        $this->_mp = fopen($this->_metaFilename, 'r');
      }
      $this->_line = 0;
      $this->_buf = null;
    }
  }

  public function isOpen() {
    return $this->_fp != null;
  }

  public function close() {
    if(is_resource($this->_fp))
      fclose($this->_fp);
    if(is_resource($this->_mp))
      fclose($this->_mp);
    $this->_fp = null;
    $this->_mp = null;
  }

  public function __destruct() {
    $this->close();
  }

  public function count() {
    rewind($this->_mp);
    return (int)fgets($this->_mp);
  }

  public function setQueryRange($from, $to) {
    $from = self::date($from);
    $to = self::date($to);

    $this->_queryFrom = $from;
    $this->_queryTo = $to;
  }

  public function setQueryFromLine($line) {
    $this->_queryFromLine = $line;
  }

  public function add($date, $data) {
    if(!is_object($date) || get_class($date) != 'DateTime') {
      throw new Exception('Date parameter passed to shard->add() was not a DateTime object: '.get_class($date));
    }
    // validate the given date is contained in this shard
    $shardDate = $this->_y.'-'.$this->_m.'-'.$this->_d;
    if($date->format('Y-m-d') != $shardDate) {
      throw new Exception('Attempted to add to a shard with the wrong date. Input:'.$date->format('Y-m-d') . ' Shard:'.$shardDate);
    }

    // format the line
    $line = $date->format('Y-m-d H:i:s.').$date->format('u');
    $line .= ' ' . json_encode($data, JSON_UNESCAPED_SLASHES);

    if($this->_fileWasJustCreated) {
      $this->_fileWasJustCreated = false;
    }

    // append the line to the file
    fwrite($this->_fp, $line."\n");

    // update the meta file
    // TODO: cache the current value to avoid needing to read it each time
    $newcount = $this->count() + 1;
    rewind($this->_mp);
    fwrite($this->_mp, $newcount."\n");
    fflush($this->_mp);
    return $newcount;
  }

  // Returns the record on the given zero-based line, or null if that line is
  // blank or past the end of the file
  public function getLine($line) {
    $this->seekLine((int)$line);
    return $this->current();
  }

  // Returns the first record with exactly this date, or null
  public function getByDate(DateTime $date) {
    $this->rewind();
    while($this->skipEmptyLines()) {
      if(self::date_eq($date, self::lineDate($this->_buf)))
        return $this->current();
      $this->advance();
    }
    return null;
  }

  public function sort() {
    // Re-sort all the records in this file by date. Line numbers will change so
    // the data will need to be re-indexed as well.
    $lines = file($this->_filename, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    usort($lines, function($a, $b){
      $dateA = new DateTime(substr($a, 0, 26), new DateTimeZone('UTC'));
      $dateB = new DateTime(substr($b, 0, 26), new DateTimeZone('UTC'));
      return !self::date_cmp($dateA, $dateB);
    });
    file_put_contents($this->_filename, implode("\n", $lines));
  }

  ////////////////////////////////////////////////////////////
  // Reading lines

  // Reads the current line if it hasn't been read yet.
  // Returns false at the end of the file.
  private function load() {
    if($this->_buf === null) {
      $line = fgets($this->_fp);
      if($line === false) {
        $this->_buf = false;
        $this->_blank = true;
      } else {
        $this->_buf = rtrim($line, "\r\n");
        $this->_blank = (trim($this->_buf) === '');
      }
    }
    return $this->_buf !== false;
  }

  // Moves to the next line. Stays put at the end of the file.
  private function advance() {
    if($this->load()) {
      $this->_line++;
      $this->_buf = null;
    }
  }

  // Blank lines can be left behind by an interrupted write. Step over them.
  // Returns whether a line with content is available.
  private function skipEmptyLines() {
    while($this->_buf !== null || $this->load()) {
      if(!$this->_blank)
        return true;
      if($this->_buf === false)
        return false;
      $this->_line++;
      $this->_buf = null;
    }
    return false;
  }

  // Moves to the given zero-based line, or to the end of the file if there
  // are fewer lines
  private function seekLine($line) {
    rewind($this->_fp);
    $this->_line = 0;
    $this->_buf = null;
    while($this->_line < $line && $this->load()) {
      $this->advance();
    }
  }

  private static function lineDate($line) {
    return new DateTime(substr($line, 0, 26), new DateTimeZone('UTC'));
  }

  ////////////////////////////////////////////////////////////
  // Iterator Interface

  #[\ReturnTypeWillChange]
  public function current() {
    if($this->_buf === null)
      $this->load();
    if($this->_blank)
      return null;

    $date = substr($this->_buf, 0, 26);
    $data = substr($this->_buf, 27);

    return Record::createFromLine($this->key(), $date, $data);
  }

  // The record ID: the shard's date followed by the zero-based line number
  #[\ReturnTypeWillChange]
  public function key() {
    return $this->_y.$this->_m.$this->_d.$this->_line;
  }

  #[\ReturnTypeWillChange]
  public function next() {
    if($this->_buf === null)
      $this->load();
    if($this->_buf !== false) {
      $this->_line++;
      $this->_buf = null;
    }
  }

  #[\ReturnTypeWillChange]
  public function valid() {
    if(!$this->skipEmptyLines())
      return false;

    if($this->_queryFrom || $this->_queryTo) {
      $date = self::lineDate($this->_buf);

      // Move to the first line that is within the range
      while($this->_queryFrom && self::date_cmp($date, $this->_queryFrom)) {
        $this->advance();
        if(!$this->skipEmptyLines())
          return false;
        $date = self::lineDate($this->_buf);
      }

      if($this->_queryTo && !self::date_cmp($date, $this->_queryTo))
        return false;
    }

    return true;
  }

  // If querying starting with a line number, rewind skips to that line
  #[\ReturnTypeWillChange]
  public function rewind() {
    $this->seekLine($this->_queryFromLine ? $this->_queryFromLine : 0);
  }
}
