<?php
/**
 * @file
 *   browser.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/classes
 */
class browser {

  private $cwd;

  function __construct($url = NULL) {
    // Set url.
    if (!empty($url)) {
      $this->setUrl($url);
    }
    // Buat object Cookie Storage
    // Buat object 
    
  }

  public function setCwd($dir) {
    try {
      if (!is_dir($dir)) {
        throw new Exception('Maaf, ini bukan direktori.');
      }
      if (!is_writable($dir)) {
        throw new Exception('Maaf, direktori ini tidak dapat ditulis.');
      }
      $this->cwd = $dir;
    }
    catch (Exception $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
  }

  public function getCwd() {
    return $this->cwd;
  }

  public function browse() {
    // Current working directory is required, if not exists
    // set working directory with current directory.
    if (!isset($this->cwd)) {
      $this->setCwd(__DIR__);
    }


    echo 'b';
  }
}
