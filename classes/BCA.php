<?php
/**
 * @file
 *   classes/BCA.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class BCA extends browser {

  // 'url' => 'https://ibank.bni.co.id',

  function __construct() {
    // Execute parent.
    $args = func_get_args();
    call_user_func_array('parent::__construct', $args);

  }

  function execute(&$object) {
    echo 'execute';
  }

}
