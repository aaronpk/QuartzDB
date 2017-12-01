<?php
namespace Quartz;

use SplFileObject, DateTime, DateTimeZone;

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
  private $_pos;

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
      // Set the fopen mode to read or write
      $mode = ($this->_db->mode == 'w' ? 'a' : 'r');
      $file = new SplFileObject($this->_filename);
      $this->_fp = $file->openFile($mode);
      $meta = new SplFileObject($this->_metaFilename);
      $this->_mp = $meta->openFile('r+');
      if($this->_fileWasJustCreated) {
        $this->_mp->fwrite("0\n");
      }
    }
  }

  public function isOpen() {
    return $this->_fp != null;
  }

  public function close() {
    $this->_fp = null;
  }

  public function count() {
    $cur = $this->_mp->rewind();
    $lines = $this->_mp->current();
    return (int)$lines;
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
    $this->_fp->fwrite($line."\n");

    // update the meta file
    $this->_mp->seek(0);
    // TODO: cache the current value to avoid needing to read it each time
    $cur = (int)trim($this->_mp->current());
    $this->_mp->seek(0);
    $newcount = ($cur+1);
    $this->_mp->fwrite($newcount."\n");
    $this->_mp->fflush();
    return $newcount;
  }

  public function getLine($line) {
    $this->_fp->seek($line);
    return $this->current();
  }

  public function getByDate(DateTime $date) {
    $this->rewind();
    do {
      $this->_fp->next();
      $line = $this->_current = $this->_fp->current();
      $curdate = substr($line, 0, 26);
      $curdate = new DateTime($curdate, new DateTimeZone('UTC'));
    } while($this->_fp->valid() && !self::date_eq($date, $curdate));

    if(self::date_eq($date, $curdate))
      return $this->current();
    else
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
  // Iterator Interface
  // Mostly pass-through to the SplFileObject

  private $_current;

  public function current() {
    if($this->_current)
      $line = $this->_current;
    else
      $line = $this->_fp->current();

    if($line == '')
      throw new Exception("Line was empty");
      
    $date = substr($line, 0, 26);
    $data = substr($line, 27);

    return Record::createFromLine($this->key(), $date, $data);
  }

  public function key() {
    $line = $this->_fp->key();
    return $this->_y.$this->_m.$this->_d.$line;
  }

  public function next() {
    return $this->_fp->next();
  }

  public function valid() {

    if($this->_queryFrom || $this->_queryTo) {
      // Check if the current line is within the range of the query
      $line = $this->_current = $this->_fp->current();
      $date = substr($line, 0, 26);
      $date = new DateTime($date, new DateTimeZone('UTC'));

      if($this->_queryFrom
        && self::date_cmp($date, $this->_queryFrom)) {
        // Seek to the first line that is within the range
        do {
          $this->_fp->next();
          $line = $this->_current = $this->_fp->current();
          $date = substr($line, 0, 26);
          $date = new DateTime($date, new DateTimeZone('UTC'));
        } while($this->_fp->valid() && self::date_cmp($date, $this->_queryFrom));
      }

      if($this->_queryTo
        && !self::date_cmp($date, $this->_queryTo)) {
        return false;
      }

      return $this->_fp->valid();
    } elseif($this->_queryFromLine) {
      $valid = $this->_fp->valid();
      // Check that the last (empty) line hasn't been reached
      if($valid) {
        $line = $this->_current = $this->_fp->current();
        if($line == '')
          $valid = false;
      }
      return $valid;
    } else {
      return $this->_fp->valid();
    }
  }

  public function rewind() {
    if($this->_queryFromLine) {
      // If querying starting with a line number, rewind should skip to that line.
      $this->_fp->rewind();
      // Because the last line doesn't have a newline, we actually have to seek
      // to the line prior to the one we want, then run next, which sets up the 
      // internal pointer properly so that valid() works.
      $this->_fp->seek($this->_queryFromLine-1);
      $this->_fp->next();
    } else {
      return $this->_fp->rewind();
    }
  }
}
