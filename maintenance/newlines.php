<?php

$folder = 'test-database';
$files = glob($folder.'/*/*/*.txt');
print_r($files);

foreach($files as $f) {
  $fp = fopen($f, 'a');
  fwrite($fp, "\n");
  fclose($fp);
}
