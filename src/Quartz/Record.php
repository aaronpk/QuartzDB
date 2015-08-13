<?php
namespace Quartz;

use DateTime;

class Record {

  private $_id;
  private $_date;
  private $_data;

  public function __get($key) {
    if(in_array($key, ['id','date','data'])) {
      return $this->{'_'.$key};
    } else {
      return null;
    }
  }

  public static function createFromLine($id, $date, $data) {
    $record = new Record();
    $record->_id = $id;
    $record->_date = new DateTime($date);
    $record->_data = json_decode($data);
    return $record;
  }

}
