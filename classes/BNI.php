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
    $step = (int) $this->getState('bni_step', 1);
    switch ($step) {
      case 1:
        $this->visit_and_check();
        break;
    }
  }

  function visit_and_check() {
  
  
    // $default_url = 'https://ibank.bni.co.id';
    // $home_link = $this->getState('bni_home_link', $default_url);
    // $this->setUrl($home_link);
    // $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');
    // $this->browse();
    // sleep(10);
    // $this->setCwd(__DIR__ . DIRECTORY_SEPARATOR . 'BNI', TRUE);
    // $url = 'http://sirip.ui.ac.id/tempe.php';
    // $this->setUrl($url);
    // $this->browse();
  
    /* $default_url = 'https://ibank.bni.co.id';
    // $default_url = 'http://www.ui.ac.id/';
    $default_url = 'http://sirip.ui.ac.id/tempe.php';
    // echo 'a';
    $home_link = $this->getState('bni_home_link', $default_url);
    // echo 'a';
    // Untuk melewati captcha kita memerlukan browser untuk device mobile.
    $this->setUrl($home_link);
    $this->headers('User-Agent', 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16');
    $this->curl(TRUE);

    $result = $this->browse();
    print_r($this->result);
    echo "\r\n";
    echo "\r\n";
    echo "\r\n"; */
    // print_r($result);

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
