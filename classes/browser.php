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

  // The $url property can change anytime because of redirect,
  // so we keep information about $original_url for reference.
  protected $original_url;

  // The variable that stored result of function parse_url().
  // Use for quickreference.
  protected $parse_url;

  // Current working directory, a place for save anything.
  private $cwd;

  // We are using PHP's Stream as default.
  // If you prefer using curl, set as TRUE with method $this->scurl(TRUE).
  private $curl = FALSE;

  // Options that needed when you browsing.
  protected $options = array();

  // Header that needed when you request HTTP.
  protected $headers = array();

  // Object result after browsing, contains info at least:
  // header response and body response.
  protected $result;

  // Object state.
  private $state;

  // Name of state file
  public $state_filename = 'state.info';

  // Object cookie.
  private $cookie;

  // Name of cookie file
  public $cookie_filename = 'cookie.csv';

  // Info about error.log
  public $error = array();

  // Info about access.log
  public $access = array();

  function __construct($url = NULL) {
    // Set url.
    if (!empty($url)) {
      $this->setUrl($url);
    }
    // Set working directory with same place.
    $this->setCwd(__DIR__);

    // Buat object Cookie Storage
    // Buat object

  }

  public function setUrl($url) {
    try {
      $parse_url = parse_url($url);
      if (!isset($parse_url['scheme'])) {
        throw new Exception('Scheme pada URL tidak diketahui: "' . $url . '".');
      }
      if (!in_array($parse_url['scheme'], array('http', 'https'))) {
        throw new Exception('Scheme pada URL hanya mendukung http atau https: "' . $url . '".');
      }
      if (!isset($parse_url['host'])) {
        throw new Exception('Host pada URL tidak diketahui: "' . $url . '".');
      }
      if (!isset($parse_url['path'])) {
        // Untuk mencocokkan info pada cookie, maka path perlu ada,
        // gunakan nilai default.
        $parse_url['path'] = '/';
      }
      // Set property $url and $original_url
      // for now, we must not edit $original_url again.
      $this->url = $url;
      if (!isset($this->original_url)) {
        $this->original_url = $url;
      }
      $this->parse_url = $parse_url;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  public function getUrl() {
    return $this->url;
  }

  public function setCwd($dir) {
    try {
      if (!is_dir($dir)) {
        throw new Exception('Directory is not exists: "' . $dir . '".');
      }
      if (!is_writable($dir)) {
        throw new Exception('Directory is not writable: "' . $dir . '".');
      }
      $this->cwd = $dir;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  public function getCwd() {
    return $this->cwd;
  }

  // Build and create file for our state.
  private function initState() {
    // Current working directory is required.
    try {
      if (!isset($this->cwd)) {
        throw new Exception('Current Working Directory not set yet, build State canceled.');
      }
      $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->state_filename;
      if (!file_exists($filename)) {
        @file_put_contents($filename, '');
      }
      if (!file_exists($filename)) {
        throw new Exception('Failed to create state file, build State canceled: "' . $filename . '".');
      }
      // Build object.
      $this->state = new stateStorage($filename);
      return TRUE;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  // Similar with drupal's variable_set() function.
  protected function setState($name, $value = NULL) {
    if (empty($this->state) && !$this->initState()) {
      return;
    }
    $this->state->set($name, $value);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
  }

  // Similar with drupal's variable_get() function.
  // If no argument passed, it means get all state.
  protected function getState($name = NULL, $default = NULL) {
    if (empty($this->state) && !$this->initState()) {
      return;
    }
    $result = $this->state->get($name, $default);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
    return $result;
  }

  // Similar with drupal's variable_del() function.
  protected function delState($name = NULL) {
    if (empty($this->state) && !$this->initState()) {
      return;
    }
    $this->state->del($name, $default);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
  }

  // cookie
  protected function initCookie() {
    // Current working directory is required.
    try {
      if (!isset($this->cwd)) {
        throw new Exception('Current Working Directory not set yet, build Cookie canceled.');
      }
      $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->cookie_filename;
      $create = FALSE;
      if (!file_exists($filename)) {
        $create = TRUE;
      }      
      else {
        $size = filesize($filename);
        if (empty($size)) {
          $create = TRUE;
          // Perlu di clear info filesize
          // atau error saat eksekusi parent::_rfile().
          // @see: http://php.net/filesize > Notes.
          clearstatcache(TRUE, $filename);
        }
      }
      if ($create) {
        $header = implode(',', $this->_cookie_field());
        file_put_contents($filename, $header . PHP_EOL);
      }
      if (!file_exists($filename)) {
        throw new Exception('Failed to create cookie file, build Cookie canceled: "' . $filename . '".');
      }
      // Build object.
      $this->cookie = new cookieStorage($filename);
      return TRUE;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }
  // cookie
  protected function setCookie() {
    if (empty($this->cookie) && !$this->initCookie()) {
      return;
    }    
    if (isset($this->result->headers['set-cookie'])) {
      // Fungsi drupal_http_request() menggabungkan seluruh set-cookie dengan glue comma
      // (lihat pada comment "Parse the response headers.").
      // Jadi kita bisa memparsing nilai set-cookie dengan delimiter comma.
      // Masalahnya ada nilai di header set-cookie yang menggunakan comma, yakni expires.
      // Contoh:
      // $string = 'PREF=ID=37b0fdc7d8efc120:FF=0:TM=1405944021:LM=1405944021:S=6saxUiBM66-kVP2D; expires=Wed, 20-Jul-2016 12:00:21 GMT; path=/; domain=.google.co.id,NID=67=M6yMDmCrGYYMc2O8AdfkjpDMLsCjxH9gmM51nhI1Gxu3UfYy6PHfak5TAswMfwNgwlqljoB5VcDMTFrgYm-18yWv0PfVwbVxIO5AGxxBAJVAbMNfGz-aL33Rxjik1uz9; expires=Tue, 20-Jan-2015 12:00:21 GMT; path=/; domain=.google.co.id; HttpOnly';
      // Sehingga, cara agar karakter comma tersebut hilang, maka
      // kita mengganti nilai expires dalam bentuk string, menjadi angka UNIX.
      $url = $this->getUrl();
      $parse_url = $this->parse_url;
      $set_cookie = $this->result->headers['set-cookie'];
      preg_match_all('/expires=([^;]*);/', $set_cookie, $match, PREG_SET_ORDER);
      if (!empty($match)) {
        foreach ($match as $value) {
          if (isset($value[0]) && isset($value[1])) {
            $time = strtotime($value[1]);
            $new = str_replace($value[1], $time, $value[0]);
            $set_cookie = str_replace($value[0], $new, $set_cookie);
          }
        }
      }
      // Setelah karakter comma hilang, sekarang barulah kita explode.
      $cookies = explode(',', $set_cookie);
      foreach ($cookies as $cookie) {
        preg_match_all('/(\w+)=([^;]*)/', $cookie, $parts, PREG_SET_ORDER);
        // print_r($parts);
        $first = array_shift($parts);
        $data = array(
          'name' => $first[1],
          'value' => $first[2],
          'created' => microtime(TRUE),
        );
        foreach ($parts as $part) {
          $key = strtolower($part[1]);
          $data[$key] = $part[2];
        }
        // default
        $data += array(
          'domain' => $parse_url['host'],
          'path' => '/',
          'expires' => NULL,
          'httponly' => preg_match('/HttpOnly/i', $cookie) ? TRUE : FALSE,
          'secure' => FALSE,
        );
        // Update our original data with the default order.
        $order = array_flip($this->_cookie_field());
        $data = array_merge($order, $data);
        // Save cookie.
        $this->cookie->set($data);
      }
    }    
  }
  
  
  // Modify (add, edit, delete) of simple array in one function.
  private function propertyArray($property, $args) {
    if (!property_exists(__CLASS__, $property)) {
      return;
    }
    switch (count($args)) {
      case 0:
        // It means get all info {$property}.
        return $this->{$property};
        break;
      case 1:
        $variable = array_shift($args);
        // If NULL, it means reset.
        if (is_null($variable)) {
          $this->{$property} = array();
        }
        // Otherwise, it means get one info {$property} by key.
        elseif (isset($this->{$property}[$variable])) {
          return $this->{$property}[$variable];
        }
        break;
      case 2:
        // It means set info option.
        $key = array_shift($args);
        $value = array_shift($args);
        try {
          if ((is_string($key) || is_numeric($key)) === FALSE) {
            throw new Exception('Key of option must string or numeric.');
          }
          if ((is_array($value) || is_object($value)) === TRUE) {
            throw new Exception('Value of option cannot array or object.');
          }
          if (is_null($value)) {
            // It means delete.
            unset($this->{$property}[$key]);
          }
          else {
            $this->{$property}[$key] = $value;
          }
        }
        catch (Exception $e) {
          $this->error[] = $e->getMessage();
        }
        break;
    }
  }

  // CRUD of property $options.
  public function options() {
    $args = func_get_args();
    return $this->propertyArray('options', $args);
  }

  // CRUD of property $headers.
  function headers() {
    $args = func_get_args();
    return $this->propertyArray('headers', $args);
  }

  // Switch if you want use curl as driver to request HTTP.
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
    echo 'browse()';
    // URL is required.
    $url = $this->getUrl();
    if (empty($url)) {
      $this->error[] = 'URL not set yet, request canceled.';
      return;
    }
    // Current working directory is required.
    if (!isset($this->cwd)) {
      $this->error[] = 'Current Working Directory not set yet, request canceled.';
      return;
    }

    // Browse.
    $this->result = $this->_browse();
    
    // Save cookie.
    if ($this->getState('cookie_save', TRUE)) {
      $this->setCookie();
    }
    
    // echo "\r\n\r\nxx\r\n\r\n";
    // var_export($this->result);
    // echo "\r\n\r\nyy\r\n\r\n";
    

  }

  protected function _browse() {
    echo '_browse()';
    $method = $this->curl ? 'curl_request' : 'drupal_http_request';    
    switch ($method) {
      case 'curl_request':
        return $this->{$method}();

      case 'drupal_http_request':
      default:
        return $this->{$method}();
    }
  }

  // Modified of function drupal_http_request in Drupal 7.
  protected function drupal_http_request() {
    if (!isset($this->timer)) {
      $this->timer = new timer;
    }
    $result = new stdClass();
    $url = $this->getUrl();
    $uri = $this->parse_url;
    $options = $this->options();
    $headers = $this->headers();
    $options += array(
      'headers' => $headers,
      'method' => 'GET',
      'data' => NULL,
      'max_redirects' => 3,
      'timeout' => 30.0,
      'context' => NULL,
    );
    // Merge the default headers.
    $options['headers'] += array(
      'User-Agent' => 'Drupal (+http://drupal.org/)',
    );
    // stream_socket_client() requires timeout to be a float.
    $options['timeout'] = (float) $options['timeout'];
    $proxy_server = $this->getState('proxy_server', '');
    $proxy_exceptions = $this->getState('proxy_exceptions', array('localhost', '127.0.0.1'));
    $is_host_not_proxy_exceptions = !in_array(strtolower($uri['host']), $proxy_exceptions, TRUE);
    if ($proxy_server && $is_host_not_proxy_exceptions) {
      // Set the scheme so we open a socket to the proxy server.
      $uri['scheme'] = 'proxy';
      // Set the path to be the full URL.
      $uri['path'] = $url;
      // Since the URL is passed as the path, we won't use the parsed query.
      unset($uri['query']);
      // Add in username and password to Proxy-Authorization header if needed.
      if ($proxy_username = $this->getState('proxy_username', '')) {
        $proxy_password = $this->getState('proxy_password', '');
        $options['headers']['Proxy-Authorization'] = 'Basic ' . base64_encode($proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : ''));
      }
      // Some proxies reject requests with any User-Agent headers, while others
      // require a specific one.
      $proxy_user_agent = $this->getState('proxy_user_agent', '');
      // The default value matches neither condition.
      if ($proxy_user_agent === NULL) {
        unset($options['headers']['User-Agent']);
      }
      elseif ($proxy_user_agent) {
        $options['headers']['User-Agent'] = $proxy_user_agent;
      }
    }
    switch ($uri['scheme']) {
      case 'proxy':
        // Make the socket connection to a proxy server.
        $socket = 'tcp://' . $proxy_server . ':' . $this->getState('proxy_port', 8080);
        // The Host header still needs to match the real request.
        $options['headers']['Host'] = $uri['host'];
        $options['headers']['Host'] .= isset($uri['port']) && $uri['port'] != 80 ? ':' . $uri['port'] : '';
        break;
      case 'http':
      case 'feed':
        $port = isset($uri['port']) ? $uri['port'] : 80;
        $socket = 'tcp://' . $uri['host'] . ':' . $port;
        // RFC 2616: "non-standard ports MUST, default ports MAY be included".
        // We don't add the standard port to prevent from breaking rewrite rules
        // checking the host that do not take into account the port number.
        $options['headers']['Host'] = $uri['host'] . ($port != 80 ? ':' . $port : '');
        break;
      case 'https':
        // Note: Only works when PHP is compiled with OpenSSL support.
        $port = isset($uri['port']) ? $uri['port'] : 443;
        $socket = 'ssl://' . $uri['host'] . ':' . $port;
        $options['headers']['Host'] = $uri['host'] . ($port != 443 ? ':' . $port : '');
        break;
      default:
        $result->error = 'invalid schema ' . $uri['scheme'];
        $result->code = -1003;
        return $result;
    }

    if (empty($options['context'])) {
      $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout']);
    }
    else {
      // Create a stream with context. Allows verification of a SSL certificate.
      $fp = @stream_socket_client($socket, $errno, $errstr, $options['timeout'], STREAM_CLIENT_CONNECT, $options['context']);
    }

    // Make sure the socket opened properly.
    if (!$fp) {
      // When a network error occurs, we use a negative number so it does not
      // clash with the HTTP status codes.
      $result->code = -$errno;
      $result->error = trim($errstr) ? trim($errstr) : t('Error opening socket @socket', array('@socket' => $socket));
      // Mark that this request failed. This will trigger a check of the web
      // server's ability to make outgoing HTTP requests the next time that
      // requirements checking is performed.
      // See system_requirements().
      $this->setState('drupal_http_request_fails', TRUE);
      return $result;
    }
    // Construct the path to act on.
    $path = isset($uri['path']) ? $uri['path'] : '/';
    if (isset($uri['query'])) {
      $path .= '?' . $uri['query'];
    }
    // Only add Content-Length if we actually have any content or if it is a POST
    // or PUT request. Some non-standard servers get confused by Content-Length in
    // at least HEAD/GET requests, and Squid always requires Content-Length in
    // POST/PUT requests.
    $content_length = strlen($options['data']);
    if ($content_length > 0 || $options['method'] == 'POST' || $options['method'] == 'PUT') {
      $options['headers']['Content-Length'] = $content_length;
    }
    // If the server URL has a user then attempt to use basic authentication.
    if (isset($uri['user'])) {
      $options['headers']['Authorization'] = 'Basic ' . base64_encode($uri['user'] . (isset($uri['pass']) ? ':' . $uri['pass'] : ':'));
    }
    //
    $request = $options['method'] . ' ' . $path . " HTTP/1.0\r\n";
    foreach ($options['headers'] as $name => $value) {
      $request .= $name . ': ' . trim($value) . "\r\n";
    }
    $request .= "\r\n" . $options['data'];
    $result->request = $request;
    // Calculate how much time is left of the original timeout value.
    $timeout = $options['timeout'] - $this->timer->read() / 1000;
    if ($timeout > 0) {
      stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
      fwrite($fp, $request);
    }
    // Fetch response. Due to PHP bugs like http://bugs.php.net/bug.php?id=43782
    // and http://bugs.php.net/bug.php?id=46049 we can't rely on feof(), but
    // instead must invoke stream_get_meta_data() each iteration.
    $info = stream_get_meta_data($fp);
    $alive = !$info['eof'] && !$info['timed_out'];
    $response = '';
    while ($alive) {
      // Calculate how much time is left of the original timeout value.
      $timeout = $options['timeout'] - $this->timer->read() / 1000;
      if ($timeout <= 0) {
        $info['timed_out'] = TRUE;
        break;
      }
      stream_set_timeout($fp, floor($timeout), floor(1000000 * fmod($timeout, 1)));
      $chunk = fread($fp, 1024);
      $response .= $chunk;
      $info = stream_get_meta_data($fp);
      $alive = !$info['eof'] && !$info['timed_out'] && $chunk;
    }
    fclose($fp);
    if ($info['timed_out']) {
      $result->code = -1;
      $result->error = 'request timed out';
      return $result;
    }
    // Parse response headers from the response body.
    // Be tolerant of malformed HTTP responses that separate header and body with
    // \n\n or \r\r instead of \r\n\r\n.
    list($response, $result->data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
    $response = preg_split("/\r\n|\n|\r/", $response);
    // Parse the response status line.
    list($protocol, $code, $status_message) = explode(' ', trim(array_shift($response)), 3);
    $result->protocol = $protocol;
    $result->status_message = $status_message;

    $result->headers = array();
    // Parse the response headers.
    while ($line = trim(array_shift($response))) {
      list($name, $value) = explode(':', $line, 2);
      $name = strtolower($name);
      if (isset($result->headers[$name]) && $name == 'set-cookie') {
        // RFC 2109: the Set-Cookie response header comprises the token Set-
        // Cookie:, followed by a comma-separated list of one or more cookies.
        $result->headers[$name] .= ',' . trim($value);
      }
      else {
        $result->headers[$name] = trim($value);
      }
    }
    $responses = array(
      100 => 'Continue',
      101 => 'Switching Protocols',
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      307 => 'Temporary Redirect',
      400 => 'Bad Request',
      401 => 'Unauthorized',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Time-out',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Large',
      415 => 'Unsupported Media Type',
      416 => 'Requested range not satisfiable',
      417 => 'Expectation Failed',
      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Time-out',
      505 => 'HTTP Version not supported',
    );
    // RFC 2616 states that all unknown HTTP codes must be treated the same as the
    // base code in their class.
    if (!isset($responses[$code])) {
      $code = floor($code / 100) * 100;
    }
    $result->code = $code;
    return $result;
  }

  //
  protected function curl_request() {
    echo 'curl_request()';
  }

  // Reference of field of cookie.
  private function _cookie_field() {
    return array(
      'domain',
      'path',
      'name',
      'value',
      'expires',
      'httponly',
      'secure',
      'created',
    );
  }
}
