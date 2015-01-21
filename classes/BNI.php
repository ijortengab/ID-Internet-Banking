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
    // $this->state_filename = 'BNI_state.info';
    // $this->cookie_filename = 'BNI_cookie.csv';
  }

  function execute() {
    $step = (int) $this->getState('bni_step', 1);
    switch ($step) {
      case 1:
        $this->visit_and_check();
        break;
    }
  }

  function visit_and_check() {
    $default_url = 'https://ibank.bni.co.id';
    $home_link = $this->getState('bni_home_link', $default_url);
    // Untuk melewati captcha kita memerlukan browser untuk device mobile.
    $this->setUrl($home_link);
    $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');    
    $this->options('aku', 'mengapa');
    $this->options('tempe', 'bacem');
    
    
    $this->browse();
    
    
    // $a = $this->options('tempe');
    // $this->options('tahu',array('busuk'));
    // $this->options('becak',(object) array('gusur'));
    // $this->options(array('bagaimana'),'bisa');
    // $a = $this->options();
    // var_dump($a);
    // $this->options('aku',NULL);
    // $a = $this->options();
    // var_dump($a);
    
    
    // $a = $this->headers();
    // var_dump($a);
    
    // $this->options();
    // print_r($this->result);
  }

}
