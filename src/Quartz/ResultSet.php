<?php
namespace Quartz;

use DateInterval, DatePeriod;

class ResultSet implements \Iterator {

  private $_db;
  private $_from;
  private $_to;
  private $_shards = [];

  private $_shardPos;

  use Helpers;

  public function __construct($db, $from, $to) {
    $this->_shardPos = 0;
    $this->_db = $db;
    $this->_from = $from;
    $this->_to = $to;

    // add all the shards to the array if they exist
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($from, $interval, $to);
    foreach($period as $dt) {
      $shard = new Shard($db, $dt->format('Y'), $dt->format('m'), $dt->format('d'));
      if($shard->exists())
        $this->_shards[] = $shard;
    }
  }

  public function current() {
    var_dump(__METHOD__);
    $this->_shards[$this->_shardPos]->current();
  }

  public function key() {
    var_dump(__METHOD__);
    $this->_shards[$this->_shardPos]->key();
  }

  public function next() {
    var_dump(__METHOD__);
    $nextInShard = $this->_shards[$this->_shardPos]->next();
    if($nextInShard === false) {
      $this->_shardPos++;
      if($this->_shardPos) {

      }
    }
  }

  public function valid() {
    var_dump(__METHOD__);

  }

  public function rewind() {
    var_dump(__METHOD__);
    $this->_shardPos = 0;

  }

}
