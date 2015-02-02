<?php
/**
 * @file
 *   demo.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */

require('classes/internetBanking.php');
require('classes/internetBankingID.php');
require('classes/parseCSV.php');
require('classes/parseINFO.php');
require('classes/parseHTTP.php');
require('classes/parseHTML.php');
require('classes/timer.php');
require('classes/cookieStorage.php');
require('classes/stateStorage.php');
require('classes/browser.php');
require('classes/BCA.php');
require('classes/BNI.php');
require('classes/Mandiri.php');

$ibank = new internetBankingID('bni');
$ibank->username = 'ijortengab';
$ibank->password = 'WYSIWYG';
$ibank->execute();

// Return float value
// Result 5001.76
// echo $ibank->balance->value();

// Return sentences of value a.k.a terbilang.
// Result lima ribu satu koma tujuh puluh enam.
// echo $ibank->balance->terbilang();

// Return Indonesia currency format by country
// Result 5.001,76
// echo $ibank->balance->format('ID')->value();

// Add symbol rupiah.
// Result Rp 5.001,76
// echo $ibank->balance->format('ID')->prefix('Rp ')->value();

// Convert to dollar.
// echo $ibank->balance->convert('USD')->format('US')->prefix('$ ')->value();


// It's recomended to see error.log
if (!empty($ibank->error)) {
  print_r($ibank->error);
}
