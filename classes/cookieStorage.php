<?php
/**
 * @file
 *   cookieStorage.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/classes
 */
class cookieStorage extends parseCSV {
  var $domain;
  var $path;
  // todo.
  // Auto update
  // If set TRUE, it will replace old value with new one.
  // Otherwise new data will appended.
  var $auto_update = FALSE;

  function __construct() {
    // Execute parent.
    $args = func_get_args();
    call_user_func_array('parent::__construct', $args);
  }

  function get($parse_url) {
    // Parse csv now.
    $this->parse();
    // Get result.
    $data = $this->data;

    // Lakukan filtering data.
    $storage = array();
    foreach($data as $key => $row) {
      // Filter domain.
      $domain_match = FALSE;
      if (substr($row['domain'], 0, 1) == '.') {
        $string = preg_quote(substr($row['domain'], 1), '/');
        if (preg_match('/.*' . $string . '/i', $parse_url['host'])) {
          $domain_match = TRUE;
        }
      }
      elseif ($row['domain'] == $parse_url['host']) {
        $domain_match = TRUE;
      }
      if (!$domain_match) {
        continue;
      }
      // Filter path.
      $path_match = FALSE;
      $string = preg_quote($row['path'], '/');
      if (preg_match('/^' . $string . '/i', $parse_url['path'])) {
        $path_match = TRUE;
      }
      if (!$path_match) {
        continue;
      }
      // Filter expires.
      $is_expired = TRUE;
      if (empty($row['expires'])) {
        $is_expired = FALSE;
      }
      elseif (time() < $row['expires']) {
        $is_expired = FALSE;
      }
      if ($is_expired) {
        continue;
      }
      // Filter duplikat dengan mengambil cookie yang paling baru.
      if (isset($storage[$row['name']]) && $storage[$row['name']]['created'] > $row['created']) {
        continue;
      }
      // Finish.
      $storage[$row['name']] = $row;
    }
    return $storage;

    // print_r($data);
    // print_r($storage);

  }
  // append cookie and save.
  function set($data) {
    // print_r($data);
    // print_r($this->data);
    // $this->save(NULL, $data, true);
    $this->save(NULL, array($data), true);
  }
}