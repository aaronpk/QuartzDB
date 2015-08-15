<?php
namespace Quartz;

use DateInterval, DatePeriod;

class ResultSet implements \Iterator {

  private $_db;
  public $_shards = [];

  private $_shardIndex = 0;

  use Helpers;

  public function __construct($db) {
    $this->_shardIndex = 0;
    $this->_db = $db;
  }

  public static function createFromDateRange($db, $from, $to) {
    $resultSet = new ResultSet($db);

    // add all the shards to the array if they exist
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($from, $interval, $to);

    $shardDates = [];
    foreach($period as $dt) {
      $shard = new Shard($db, $dt->format('Y'), $dt->format('m'), $dt->format('d'));
      if($shard->exists()) {
        // Only set the query range if it overlaps this shard
        if($from->format('Y-m-d') == $dt->format('Y-m-d')
          || $to->format('Y-m-d') == $dt->format('Y-m-d')) {
          $shard->setQueryRange($from, $to);
        }
        $resultSet->_shards[] = $shard;
        $shardDates[] = $dt->format('Y-m-d');
      }
    }
    // The last shard may not have been reached by the iterator, for example if 
    // the from timestamp starts at 17:00 and the end timestamp is 10:00
    if(!in_array($to->format('Y-m-d'), $shardDates)) {
      $shard = new Shard($db, $to->format('Y'), $to->format('m'), $to->format('d'));
      if($shard->exists()) {
        $shard->setQueryRange($from, $to);
        $resultSet->_shards[] = $shard;
      }
    }

    return $resultSet;
  }

  public function addShard($shard) {
    $this->_shards[] = $shard;
  }

  private function currentShard() {
    if(array_key_exists($this->_shardIndex, $this->_shards))
      return $this->_shards[$this->_shardIndex];
    else
      return null;
  }

  ////////////////////////////////////////////////////////////
  // Iterator Interface

  public function current() {
    if(!$this->currentShard())
      return null;

    return $this->currentShard()->current();
  }

  public function key() {
    if(!$this->currentShard())
      return null;

    return $this->currentShard()->key();
  }

  public function next() {
    if(!$this->currentShard())
      return false;

    // Always just run next() on the current shard, which means we 
    // won't know if that shard has a valid record until it's checked with valid()
    return $this->currentShard()->next();
  }

  public function valid() {
    if(!$this->currentShard())
      return false;

    $currentValid = $this->currentShard()->valid();
    if($currentValid) {
      return $currentValid;
    } else {
      // Check the next shard
      $this->_shardIndex++;
      if($this->currentShard()) {
        $this->currentShard()->init();
        $this->currentShard()->rewind();
        return $this->currentShard()->valid();
      } else {
        return false;
      }
    }
  }

  public function rewind() {
    $this->_shardIndex = 0;
    if($this->currentShard()) {
      $this->currentShard()->init();
      return $this->currentShard()->rewind();
    }
  }

}
