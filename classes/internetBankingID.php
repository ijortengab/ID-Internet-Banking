<?php
/**
 * @file
 *   classes/internetBankingID.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class internetBankingID extends internetBanking {

  function reference() {
    return array(
      'bni' => array(
        'handler' => 'BNI',
        'info' => array(
          'name' => 'Bank Negara Indonesia',
          'homepage' => 'http://bni.co.id',
        ),
      ),
    );
  }
}
