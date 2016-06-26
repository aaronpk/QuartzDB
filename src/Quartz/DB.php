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

  public static function UTC() {
    static $utc;
    if(!isset($utc))
      $utc = new DateTimeZone('UTC');
    return $utc;
  }

  public function lastShardFile() {
    return $this->_path . '/lastshard.txt';
  }

  public function close() {
    foreach($this->_shards as $shard) {
      $shard->close();
    }
  }

  public function add($date, $data) {
    if($this->_mode != 'w')
      throw new Exception('This connection is read-only');

    $date = self::date($date);
    $shard = $this->shardForDate($date);

    if(!$shard->isOpen())
      $shard->init();

    return $shard->add($date, $data);
  }

  public function queryRange($from, $to) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

    $from = self::date($from);
    $to = self::date($to);

    if($from > $to) {
      throw new Exception('From must be an earlier date than To');
    }

    return ResultSet::createFromDateRange($this, $from, $to);
  }

  // TODO: Currently this only retrieves the last n records of the last shard.
  // Ideally this would start seeking backwards in all the shards to fetch the
  // actual last n records in the DB.
  public function queryLast($n) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

    $lastShard = self::lastShard();

    if(!$lastShard->isOpen())
      $lastShard->init();

    $lines = $lastShard->count();
    $from = max($lines - $n, 0);

    $resultSet = new ResultSet($this);
    if($from > 0)
      $lastShard->setQueryFromLine($from);
    $resultSet->addShard($lastShard);
    return $resultSet;
  }

  public function getByDate($date) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

    $date = self::date($date);

    $shard = $this->shardForDate($date);

    if(!$shard->exists())
      return null;

    if(!$shard->isOpen())
      $shard->init();

    return $shard->getByDate($date);
  }

  public function getByID($id) {
    if($this->_mode != 'r')
      throw new Exception('This connection is write-only');

    if(!preg_match('/^(\d{4})(\d{2})(\d{2})(\d+)$/', $id, $match))
      throw new Exception('Invalid ID');

    $shard = $this->getShard($match[1],$match[2],$match[3]);

    if(!$shard->exists())
      return null;

    if(!$shard->isOpen())
      $shard->init();

    return $shard->getLine($match[4]);
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

  public function lastShard() {
    $key = file_get_contents($this->lastShardFile());
    if(!array_key_exists($key, $this->_shards)) {
      $date = new DateTime($key, self::UTC());
      $shard = Shard::createFromDate($this, $date);
    } else {
      $shard = $this->_shards[$key];
    }
    return $shard;
  }

}
