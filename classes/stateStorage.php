<?php
/**
 * @file
 *   stateStorage.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/ID-Internet-Banking
 */
class stateStorage extends parseINFO {

  var $filename;

  var $error = array();

  function __construct($filename = NULL) {
    $this->filename = $filename;
  }
  // Read.
  protected function read() {
    try {
      if (!file_exists($this->filename)) {
        return;
      }
      if (($content = @file_get_contents($this->filename)) === FALSE) {
        throw new Exception('Failed to get content from: "' . $this->filename . '".');
      }
      $info = $this->decode($content);
      return $info;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }
  // Save.
  protected function save($info) {
    $content = $this->encode($info);
    try {
      if (@file_put_contents($this->filename, $content) === FALSE) {
        throw new Exception('Failed to write content to: "' . $this->filename . '".');
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }
  //
  public function set($name, $value) {
    try {
      if ((is_string($name) || is_numeric($name)) === FALSE) {
        throw new Exception('State name must string or numeric.');
      }
      $info = $this->read();
      if (isset($info)) {
        $info[$name] = $value;
        $this->save($info);
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  public function get($name = NULL, $default_value = NULL) {
    $info = $this->read();
    if (isset($info) && is_null($name)) {
      return $info;
    }
    elseif (isset($info) && isset($info[$name])) {
      return $info[$name];
    }
    else {
      return $default_value;
    }
  }

  public function del($name) {
    $info = $this->read();
    if (isset($info) && isset($info[$name])) {
      unset($info[$name]);
      $this->save($info);
    }
  }
}
