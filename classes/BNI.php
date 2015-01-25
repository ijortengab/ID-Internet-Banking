<?php

/**
 * @file
 *   BNI.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class BNI extends browser {

  function __construct() {
    // Execute parent.
    $args = func_get_args();
    call_user_func_array('parent::__construct', $args);

    // Custom for BNI only.
    $this->setCwd(__DIR__ . DIRECTORY_SEPARATOR . 'BNI', TRUE);
  }

  function execute() {
    $step = $this->getState('bni_step', 'visit_home');
    $this->{$step}();
  }

  function visit_home() {
    $default_url = 'https://ibank.bni.co.id';
    $home_link = $this->getState('bni_url_home', $default_url);
    $this->setUrl($home_link);
    $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');
    $this->options('cookie_retrieve', TRUE)
         ->options('cookie_save', TRUE)
         ->options('cache_save', TRUE)
         ->options('history_save', TRUE)
         ->options('follow_location', TRUE)
         ->browse();
    
    // Todo, lakukan validate disini.
    // jika valid, maka nantinya baru diset step berikutnya.
    $states = array(
      'bni_step' => 'search_link_entry',
      'bni_cache' => $this->cache,
      'bni_cache_expired' => date('Y-m-d H:i:s O', strtotime('+5 minutes')),
    );
    $this->setState($states);
  }

  function search_link_entry() {
    try {
      // Validate, jika cache expired, maka mundur step.
      $cache_expired = strtotime($this->getState('bni_cache_expired'));
      if (time() > $cache_expired) {
        $this->delState('bni_cache_expired')
             ->delState('bni_cache')
             ->setState('bni_step', 'visit_home');
        throw new Exception('Cache expires, backward from "search_link_entry" to "visit_home".');
      }
      // Parsing dan cari url.
      $html = new parseHTML($this->getState('bni_cache'));
      $url = $html->getLinkById('RetailUser');
      if (!isset($url)) {
        throw new Exception('Url entry tidak ditemukan.');
      }
      $states = array(
        'bni_step' => 'visit_link_entry',
        'bni_url_entry' => $url,
      );
      $this->setState($states);
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function visit_link_entry() {      
    $this->setUrl($this->getState('bni_url_entry'));
    $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');
    $this->options('cookie_retrieve', TRUE)
         ->options('cookie_save', TRUE)
         ->options('cache_save', TRUE)
         ->options('history_save', TRUE)
         ->options('follow_location', TRUE)
         ->browse();
    $states = array(
      'bni_step' => 'login',
      'bni_cache' => $this->cache,
      'bni_cache_expired' => date('Y-m-d H:i:s O', strtotime('+5 minutes')),
    );
    $this->setState($states);
  }
  
  function login() {
    $url = "http://localhost/post.php";
    $this->post('username', 'a')->post('password', 'a')->post('submit', 'a');
    // $a =$this->post();
    // var_dump($a);    
    $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');
    $this->curl(true)->setUrl($url)->browse();
    print_r($this->result);
    // var_dump(true);
    // var_dump(false);
    
    
  }

}
