<?php
namespace Quartz;

use DateTime, DateTimeZone;

trait Helpers {

  public function shardForDate($date) {
    $date = self::date($date);
    $y = $date->format('Y');
    $m = $date->format('m');
    $d = $date->format('d');

    $key = $date->format('Y-m-d');

    if(!array_key_exists($key, $this->_shards)) {
      $shard = new Shard($this, $y, $m, $d);
      $this->_shards[$key] = $shard;
    } else {
      $shard = $this->_shards[$key];
    }

    return $shard;
  }

  private function date($input) {
    if(is_string($input)) {
      $date = new DateTime($input);
      $date->setTimeZone(new DateTimeZone('UTC'));
      return $date;
    } elseif(is_object($input) && get_class($input) == 'DateTime') {
      $input->setTimeZone(new DateTimeZone('UTC'));
      return $input;
    } else {
      throw new \Exception("Invalid date");
    }
  }


}
