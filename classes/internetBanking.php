<?php
/**
 * @file
 *   internetBanking.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
abstract class internetBanking {

  // Reference Information.
  abstract protected function reference();

  // Bank object.
  protected $bank;

  // Account information.
  public static $username;
  
  public static $password;

  // Result information.
  public static $balance;
  
  public $history;

  public $error = array();
  

  function __construct($bank) {
    // We need validation first.
    try {
      if (!isset($this->reference()[$bank])) {
        throw new Exception('Referensi bank tidak ditemukan.');
      }
      if (!isset($this->reference()[$bank]['handler'])) {
        throw new Exception('Class handler tidak ditemukan.');
      }
      $handler = $this->reference()[$bank]['handler'];
      $this->bank = new $handler;
    }
    catch (Exception $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
  }

  // lakukan execute untuk tempat akhir semua tujuan.
  function execute() {
    try {
      if (empty($this->bank)) {
        throw new Exception('Object class belum didefinisikan.');
      }
      // Execute.
      $this->bank->execute();
      // Get info error, balance, and history.
      $this->error = array_merge($this->error, $this->bank->error);
    }
    catch (Exception $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
  }
}
