<?php
namespace Quartz;

use DateTime, DateTimeZone;

class DB {

  private $_path;
  private $_mode;
  private $_shards = [];

  use Helpers;

  public function __construct($path, $mode) {
    $this->_path = $path;
    $this->_mode = $mode;
  }

  public function __get($key) {
    switch($key) {
      case 'path':
        return $this->_path;
      case 'mode':
        return $this->_mode;
    }
  }

  public function add($date, $data) {
    if($this->_mode != 'w')
      throw new Exception('This connection is read-only');

    $date = self::date($date);
    $shard = $this->shardForDate($date);
    if(!$shard->isOpen())
      $shard->init();
    $shard->add($date, $data);
  }

  public function queryRange($from, $to) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

    $from = self::date($from);
    $to = self::date($to);

    if($from > $to) {
      throw new Exception('From must be an earlier date than To');
    }

    return new ResultSet($this, $from, $to);
  }

  public function getByDate($date) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

  }

  public function getByID($y, $m, $d, $i) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

  }

  public function getShard($y, $m, $d) {
    $key = $y.'-'.$m.'-'.$d;
    if(!array_key_exists($key, $this->_shards)) {
      $shard = new Shard($this, $y, $m, $d);
      $this->_shards[$key] = $shard;
    } else {
      $shard = $this->_shards[$key];
    }
    return $shard;
  }

}
