<?php
namespace Quartz;

use SplFileObject, DateTime;

class Shard implements \Iterator {

  private $_db;
  private $_path;
  private $_filename;
  private $_fp = null;

  private $_fileWasJustCreated = false;

  private $_y;
  private $_m;
  private $_d;

  private $_queryFrom;
  private $_queryTo;
  private $_pos;

  use Helpers;

  public function __construct($db, $y, $m, $d) {
    $this->_db = $db;
    // get the path for this shard
    $this->_path = $db->path . '/' . $y . '/' . $m . '/';
    // get the filename for this shard
    $this->_filename = $this->_path . $d . '.txt';

    $this->_y = $y;
    $this->_m = $m;
    $this->_d = $d;
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
      $this->_fileWasJustCreated = true;
    }

    // open the file pointer
    if($this->exists()) {      
      // Set the fopen mode to read or write
      $mode = ($this->_db->mode == 'w' ? 'a' : 'r');
      $file = new SplFileObject($this->_filename);
      $this->_fp = $file->openFile($mode);
    }
  }

  public function isOpen() {
    return $this->_fp != null;
  }

  public function close() {
    $this->_fp = null;
  }

  public function setQueryRange($from, $to) {
    $from = self::date($from);
    $to = self::date($to);

    $this->_queryFrom = $from;
    $this->_queryTo = $to;
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
    $line .= ' ' . json_encode($data);

    if($this->_fileWasJustCreated) {
      $this->_fileWasJustCreated = false;
      $newline = '';
    } else {
      $newline = "\n";
    }

    // append the line to the file
    $this->_fp->fwrite($newline.$line);
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
      $date = new DateTime($date);

      if($this->_queryFrom
        && self::date_cmp($date, $this->_queryFrom)) {
        // Seek to the first line that is within the range
        do {
          $this->_fp->next();
          $line = $this->_current = $this->_fp->current();
          $date = substr($line, 0, 26);
          $date = new DateTime($date);
        } while($this->_fp->valid() && self::date_cmp($date, $this->_queryFrom));
      }

      if($this->_queryTo
        && !self::date_cmp($date, $this->_queryTo)) {
        return false;
      }

      return $this->_fp->valid();
    } else {
      return $this->_fp->valid();
    }
  }

  public function rewind() {
    return $this->_fp->rewind();
  }
}
