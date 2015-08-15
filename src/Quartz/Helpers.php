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
      try {
        $date = new DateTime($input);
        $date->setTimeZone(new DateTimeZone('UTC'));
        return $date;
      } catch(\Exception $e) {
        throw new \Exception("Invalid date string");
      }
    } elseif(is_object($input) && get_class($input) == 'DateTime') {
      $input->setTimeZone(new DateTimeZone('UTC'));
      return $input;
    } else {
      throw new \Exception("Invalid date");
    }
  }

  private static function date_cmp($a, $b) {
    // if $d1 and $d2 are DateTime objects with microsecond precision, $d1 < $d2 
    // does not take in to account the microseconds when comparing. This is a workaround.
    $a = $a->format('U')*1000000 + $a->format('u');
    $b = $b->format('U')*1000000 + $b->format('u');
    return $a < $b;
  }

  public static function date_eq($a, $b) {
    // Comparing DateTime objects with == does not take into account microseconds.
    $a = $a->format('U')*1000000 + $a->format('u');
    $b = $b->format('U')*1000000 + $b->format('u');
    return $a == $b;
  }

}
