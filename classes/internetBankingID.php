<?php
/**
 * @file
 *   internetBankingID.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class internetBankingID extends internetBanking {

  // Handler adalah class harus ada property sbb:
  // - error, berupa array, semua catch ditaruh di property ini.
  // - balance, berupa object.
  // - history, berupa object.
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
