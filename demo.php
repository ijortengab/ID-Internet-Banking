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
require('classes/browser.php');
  require('classes/BCA.php');
  require('classes/BNI.php');
  require('classes/Mandiri.php');
require('classes/parseCSV.php');
  require('classes/cookieStorage.php');
require('classes/parseINFO.php');
  require('classes/stateStorage.php');
require('classes/parseHTML.php');

$ibank = new internetBankingID('bni');
$ibank->username = 'ijortengab';
$ibank->password = 'WYSIWYG';
$ibank->execute();

echo $ibank->balance;


// $ibank->set('roji', 'cinta')->set('aku', 'mengapa');
// $browser = new browser()->browse();
// print_r($browser);


// echo $ibank->username;
// $ibank = new internetBankingID();
// $ibank->set('bank', 'bni');
// $ibank->set('username', 'USERNAME');
// $ibank->set('password', 'PASSWORD');
// $ibank->execute();
// print_r($ibank->bank);
// print_r($ibank->options);