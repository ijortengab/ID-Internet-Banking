<?php

class timer {
  function __construct() {
    $this->start = microtime(TRUE);
  }
  function read() {
    $stop = microtime(TRUE);
    $diff = round(($stop - $this->start) * 1000, 2);
    if (isset($this->time)) {
      $diff += $this->time;
    }
    return $diff;
  }
}