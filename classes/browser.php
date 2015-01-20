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

  // The main URL.
  private $url;

  // The variable that stored result of function parse_url().
  // Use for quickreference.
  public $parse_url;

  // Current working directory, a place for save anything.
  private $cwd;

  // We are using PHP's Stream as default.
  // If you prefer using curl, set as TRUE.
  private $curl = FALSE;

  function __construct($url = NULL) {
    // Set url.
    if (!empty($url)) {
      $this->setUrl($url);
    }
    // Buat object Cookie Storage
    // Buat object

  }

  public function setUrl($url) {

  }

  public function getUrl($url) {
    return $this->url;
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

  public function curl($switch = TRUE) {
    if ($switch && function_exists('curl_init')) {
      return $this->curl = TRUE;
    }
    else {
      return $this->curl = FALSE;
    }
  }

  // Main function.
  public function browse() {
    // Current working directory is required, if not exists
    // set working directory with current directory.
    if (!isset($this->cwd)) {
      $this->setCwd(__DIR__);
    }

    $method = $this->curl ? 'curl_request' : 'drupal_http_request';
    switch ($method) {
      case 'curl_request':
        $result = $this->{$method}();
        break;

      case 'drupal_http_request':
      default:
        $result = $this->{$method}();
        break;
    }




    echo 'b';
  }

  // Modified of function drupal_http_request in Drupal 7.
  protected function drupal_http_request() {
    echo 'drupal_http_request()';
  }

  //
  protected function curl_request() {
    echo 'curl_request()';
  }

}
