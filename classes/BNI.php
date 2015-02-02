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

  function browse() {
    // Setting our default options and headers.
    $user_agent = $this->getState('bni_user_agent');
    if (empty($user_agent)) {
      $user_agent = $this->getUserAgent(array('mobile' => TRUE));
      $this->setState('bni_user_agent', $user_agent);
    }
    $this->headers('User-Agent', $user_agent)
         ->options('cookie_retrieve', TRUE)
         ->options('cookie_save', TRUE)
         ->options('cache_save', TRUE)
         ->options('history_save', TRUE)
         ->options('follow_location', TRUE);

    // Execute parent.
    return parent::browse();
  }

  function execute() {
    $step = $this->getState('bni_step', 'visit_home');
    $this->{$step}();
    // Cek apakah error terjadi.
    // Jika iya, maka kembalikan step ke semula,
    // serta beri delay 1 menit agar menghindari beban server.
    if (!empty($this->error) && $step != 'visit_home') {
      $states = array(
        'bni_step' => NULL,
        'bni_delay_execute' => TRUE,
        'bni_delay_expired' => date('Y-m-d H:i:s O', strtotime('+1 minutes')),
      );
      $this->setState($states);
    }
  }

  function visit_home() {
    try {
      $default_url = 'https://ibank.bni.co.id';
      // Cek apakah ada delay, delay disetting otomatis 1 menit
      // apabila salah satu step terjadi error atau
      // tugas telah dijalankan dengan sempurna.
      $is_delay = $this->getState('bni_delay_execute', FALSE);
      if ($is_delay) {
        $is_delay_expired = time() > strtotime($this->getState('bni_delay_expired'));
        if ($is_delay_expired) {
          $this->setState('bni_delay_execute', FALSE);
        }
        else {
          throw new Exception('Sedang delay, tunggu beberapa saat lagi.');
        }
      }

      $home_link = $this->getState('bni_url_home', $default_url);
      $this->setUrl($home_link)->browse();

      // Todo, lakukan validate disini.
      // jika valid, maka nantinya baru diset step berikutnya.
      $states = array(
        'bni_step' => 'search_link_entry',
        'bni_cache' => $this->cache,
        'bni_cache_expired' => date('Y-m-d H:i:s O', strtotime('+5 minutes')),
      );
      $this->setState($states);
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  function search_link_entry() {
    try {
      // Validate, jika cache expired, maka error.
      $cache_expired = strtotime($this->getState('bni_cache_expired'));
      if (time() > $cache_expired) {
        throw new Exception('Cache expires.');
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
    try {
      // Pastikan file ada dan bisa kebaca karena ada kasus
      // dimana file ternyata tidak bisa kebaca karena settingan
      // otomatis oleh server.
      $file_cache = $this->getState('bni_cache');
      if (!file_exists($file_cache)) {
        throw new Exception('File cache tidak ditemukan.');
      }
      if (!is_readable($file_cache)) {
        throw new Exception('File cache tidak bisa terbaca.');
      }
      // Validate, jika cache expired, maka mundur step.
      $cache_expired = strtotime($this->getState('bni_cache_expired'));
      if (time() > $cache_expired) {
        throw new Exception('Cache expires, backward from "search_link_entry" to "visit_home".');
      }
      $contents = file_get_contents($file_cache);
      $html = new parseHTML($contents);
      // $name = $html->attr('lang');
      $name = $html->find( "li.third-item" );
      // $name = $html->find('title')->text();
      echo 'print_r($name): '; print_r($name);
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

}


