<?php

class timer {
  
  var $count_down;
  
  function __construct($count_down = NULL) {
    if (is_int($count_down)) {
      $this->count_down = $count_down;
    }
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
  // check count down.
  // return sisa waktu
  function countdown() {
    return round($this->count_down - ($this->read() / 1000));    
  }
}