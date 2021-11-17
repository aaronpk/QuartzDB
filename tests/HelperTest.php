<?php

class HelperTest extends \PHPUnit\Framework\TestCase {

  use Quartz\Helpers;

  public function setUp(): void {
    date_default_timezone_set('UTC');
  }

  public function testDateCmpSecondPrecision() {
    $a = new DateTime('2015-01-01 00:00:00 +0000');
    $b = new DateTime('2015-01-01 00:00:01 +0000');
    $result = self::date_cmp($a, $b);
    $this->assertEquals(true, $result);
  }

  public function testDateCmpMicrosecondPrecision() {
    $a = new DateTime('2015-01-01 00:00:01.000001 +0000');
    $b = new DateTime('2015-01-01 00:00:01.000002 +0000');
    $result = self::date_cmp($a, $b);
    $this->assertEquals(true, $result);
  }

  public function testDateEqualSecondPrecision() {
    $a = new DateTime('2015-01-01 00:00:00 +0000');
    $b = new DateTime('2015-01-01 00:00:01 +0000');
    $result = self::date_eq($a, $b);
    $this->assertEquals(false, $result);

    $a = new DateTime('2015-01-01 00:00:01 +0000');
    $b = new DateTime('2015-01-01 00:00:01 +0000');
    $result = self::date_eq($a, $b);
    $this->assertEquals(true, $result);
  }

  public function testDateEqualMicrosecondPrecision() {
    $a = new DateTime('2015-01-01 00:00:01.000001 +0000');
    $b = new DateTime('2015-01-01 00:00:01.000002 +0000');
    $result = self::date_eq($a, $b);
    $this->assertEquals(false, $result);

    $a = new DateTime('2015-01-01 00:00:01.000003 +0000');
    $b = new DateTime('2015-01-01 00:00:01.000003 +0000');
    $result = self::date_eq($a, $b);
    $this->assertEquals(true, $result);
  }

  public function testCreateDateObjectFromString() {
    $input = '2015-01-01 12:34:56 +0000';
    $date = self::date($input);
    $formatted = $date->format('Y-m-d H:i:s O');
    $this->assertEquals($input, $formatted);
  }

  public function testCreateDateObjectFromObject() {
    $input = '2015-01-01 12:34:56 +0000';
    $inputDate = new DateTime($input);
    $date = self::date($inputDate);
    $formatted = $date->format('Y-m-d H:i:s O');
    $this->assertEquals($input, $formatted);
  }

  public function testFailsForInvalidDate() {
    $input = 'foobar';
    try {
      $date = self::date($input);
    } catch(Exception $e) {
    }
    $this->assertEquals('Invalid date string', $e->getMessage());

    $input = new StdClass;
    try {
      $date = self::date($input);
    } catch(Exception $e) {
    }
    $this->assertEquals('Invalid date', $e->getMessage());
  }

}
