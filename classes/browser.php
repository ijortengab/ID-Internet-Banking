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
  /*
  options used by curl
  encoding (default: gzip, deflate)
  timeout (default: 30)
  referer


  options used by stream
  'method' => 'GET',
  'data' => NULL,
  'max_redirects' => 3,
  'timeout' => 30.0,
  'context' => NULL,


   */
  protected $options = array();

  // Header that needed when you request HTTP.
  protected $headers = array();

  // Post
  protected $post = array();

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

  // Name of history file.
  public $history_filename = 'access.log';

  // Name of cache file.
  public $cache_filename = 'cache.html';

  // The real of cache file_name that has storage,
  // because we use autoincreament suffix in filename
  // to void overwriting file cache.
  var $cache;

  // Info about error.log
  public $error = array();

  // Info about access.log
  public $access = array();

  // storage to count time browsing.
  var $timer;

  function __construct($url = NULL) {
    // Set url.
    if (!empty($url)) {
      $this->setUrl($url);
    }
    // Set working directory with same place.
    $this->setCwd(__DIR__);

    // Prefered using curl.
    $this->curl(TRUE);
    $this->options('encoding', 'gzip, deflate');
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

      return $this;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  public function getUrl() {
    return $this->url;
  }

  protected function mkdir($dir) {
    try {
      if (file_exists($dir)) {
        $something = 'something';
        if (is_file($dir)) {
          $something = 'file';
        }
        if (is_link($dir)) {
          $something = 'link';
        }
        throw new Exception('Create directory cancelled, ' . $something . ' has same name and exists: "' . $dir . '".');
      }
      $mode = $this->getState('file_chmod_directory', 0775);
      if (@mkdir($dir, $mode, TRUE) === FALSE) {
        throw new Exception('Create directory failed: "' . $dir . '".');
      }
      return TRUE;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  // This functin should fire in construct or soon after call this object.
  public function setCwd($dir, $autocreate = FALSE) {
    try {
      if (!is_dir($dir) && !$autocreate) {
        throw new Exception('Set directory failed, directory not exists: "' . $dir . '".');
      }
      if (!is_dir($dir) && $autocreate && !$this->mkdir($dir)) {
        throw new Exception('Set directory failed, trying to create but failed: "' . $dir . '".');
      }
      if (!is_writable($dir)) {
        throw new Exception('Set directory failed, directory is not writable: "' . $dir . '".');
      }
      // Sebelum set.
      // Copy file-file yang berada di directory lama.
      $old = $this->getCwd();
      $new = $dir;
      $files = array(
        $this->state_filename,
        $this->cookie_filename,
        $this->history_filename,
      );
      foreach ($files as $file) {
        if (file_exists($old . DIRECTORY_SEPARATOR . $file)) {
          rename($old . DIRECTORY_SEPARATOR . $file, $new . DIRECTORY_SEPARATOR . $file);
        }
      }

      // Directory sudah siap diset.
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
  protected function initState($autocreate = FALSE) {
    // Current working directory is required.
    try {
      $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->state_filename;
      if (!file_exists($filename) && !$autocreate) {
        return FALSE;
      }
      if (!isset($this->cwd)) {
        throw new Exception('Current Working Directory not set yet, build State canceled.');
      }
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
    if (empty($this->state) && !$this->initState(TRUE)) {
      return;
    }
    $this->state->set($name, $value);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
    // Return object to make a thread.
    return $this;
  }

  // Similar with drupal's variable_get() function.
  // If no argument passed, it means get all state.
  protected function getState($name = NULL, $default = NULL) {
    if (empty($this->state) && !$this->initState()) {
      return $default;
    }
    $result = $this->state->get($name, $default);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
    return $result;
  }

  // Similar with drupal's variable_del() function.
  // If no argument passed, it means del all state.
  protected function delState($name = NULL) {
    if (empty($this->state) && !$this->initState()) {
      return;
    }
    // todo, if name == NULL, maka destroy,
    // tapi sebelumnya buat backup dulu agar tidak menyesal.

    $this->state->del($name);
    // Merge error.log.
    $this->error = array_merge($this->error, $this->state->error);
    // Return object to make a thread.
    return $this;
  }

  // Build and create file for our cookie.
  protected function initCookie($autocreate = FALSE) {
    // Current working directory is required.
    try {
      $filename = $this->cwd . DIRECTORY_SEPARATOR . $this->cookie_filename;
      if (!file_exists($filename) && !$autocreate) {
        return FALSE;
      }
      if (!isset($this->cwd)) {
        throw new Exception('Current Working Directory not set yet, build Cookie canceled.');
      }
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
      // Jangan masukkan $filename sebagai argument saat calling cookieStorage,
      // agar tidak dilakukan parsing. Parsing hanya dilakukan saat melakukan
      // method get.
      $this->cookie = new cookieStorage;
      $this->cookie->file = $filename;
      return TRUE;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  // Save cookie from header response to file csv.
  protected function setCookie() {
    if (empty($this->cookie) && !$this->initCookie(TRUE)) {
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

  // Retrieve cookie from file csv than set to header request.
  protected function getCookie() {
    if (empty($this->cookie) && !$this->initCookie()) {
      return;
    }
    $cookies = $this->cookie->get($this->parse_url);
    if (!empty($cookies)) {
      $old = $this->headers('Cookie');
      isset($old) or $old = '';
      foreach($cookies as $cookie) {
        if (!empty($old)) {
          $old .= '; ';
        }
        $old .= $cookie['name'] . '=' . $cookie['value'];
      }
      $this->headers('Cookie', $old);
    }
  }

  protected function history() {
    $filename = $this->getCwd() . DIRECTORY_SEPARATOR . $this->history_filename;
    $request = $this->result->request;
    $response = $this->result->headers_raw;
    $content = '';
    $content .= 'REQUEST:' . "\t";
    $content .= preg_replace("/\r\n|\n|\r/", "\t", $request);
    $content .= PHP_EOL;
    $content .= 'RESPONSE:' . "\t";
    $content .= implode("\t", $response);
    $content .= PHP_EOL;
    if ($this->options('cache_save')) {
      $content .= 'CACHE:' . "\t";
      $content .= $this->cache;
      $content .= PHP_EOL;
    }
    $content .= PHP_EOL;
    try {
      if (@file_put_contents($filename, $content, FILE_APPEND) === FALSE) {
        throw new Exception('Failed to write content to: "' . $this->filename . '".');
      }
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }

  protected function cache() {
    if (empty($this->result->data)) {
      return;
    }
    $basename = $this->cache_filename;
    $directory = $this->getCwd();
    $filename = $this->filename_uniquify($basename, $directory);
    $content = $this->result->data;
    try {
      if (@file_put_contents($filename, $content) === FALSE) {
        throw new Exception('Failed to write content to: "' . $this->filename . '".');
      }
      // Set a new name.
      $this->cache = $filename;
    }
    catch (Exception $e) {
      $this->error[] = $e->getMessage();
    }
  }
  // Source from Drupal 7's function file_create_filename().
  private function filename_uniquify($basename, $directory) {
    // Strip control characters (ASCII value < 32). Though these are allowed in
    // some filesystems, not many applications handle them well.
    $basename = preg_replace('/[\x00-\x1F]/u', '_', $basename);
    if (substr(PHP_OS, 0, 3) == 'WIN') {
      // These characters are not allowed in Windows filenames
      $basename = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $basename);
    }

    // A URI or path may already have a trailing slash or look like "public://".
    if (substr($directory, -1) == DIRECTORY_SEPARATOR) {
      $separator = '';
    }
    else {
      $separator = DIRECTORY_SEPARATOR;
    }

    $destination = $directory . $separator . $basename;

    if (file_exists($destination)) {
      // Destination file already exists, generate an alternative.
      $pos = strrpos($basename, '.');
      if ($pos !== FALSE) {
        $name = substr($basename, 0, $pos);
        $ext = substr($basename, $pos);
      }
      else {
        $name = $basename;
        $ext = '';
      }

      $counter = 0;
      do {
        $destination = $directory . $separator . $name . '_' . $counter++ . $ext;
      } while (file_exists($destination));
    }

    return $destination;
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
        // If Array, it meanse replace all value with that array.
        elseif (is_array($variable)) {
          $this->{$property} = $variable;
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
          // Kembalikan lagi object, agar bisa set banyak.
          return $this;
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
  public function headers() {
    $args = func_get_args();
    return $this->propertyArray('headers', $args);
  }

  // CRUD of property $headers.
  public function post() {
    $args = func_get_args();
    return $this->propertyArray('post', $args);
  }

  // Switch if you want use curl as driver to request HTTP.
  // Curl support to compressed response.
  public function curl($switch = TRUE) {
    if ($switch && function_exists('curl_init')) {
      $this->curl = TRUE;
    }
    else {
      $this->curl = FALSE;
    }
    return $this;
  }

  // Main function.
  public function browse() {
    if (!isset($this->timer)) {
      $this->timer = new timer;
    }
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
    // Use default option.
    $this->options($this->options() + $this->_default_options());

    // Retrieve cookie.
    if ($this->options('cookie_retrieve')) {
      $this->getCookie();
    }

    // Browse.
    $this->result = $this->_browse();

    // Save cookie.
    if ($this->options('cookie_save')) {
      $this->setCookie();
    }

    // Save cache.
    if ($this->options('cache_save')) {
      $this->cache();
    }

    // Save history.
    if ($this->options('history_save')) {
      $this->history();
    }

    // Save info error.
    if (isset($this->result->error)) {
      $this->error[] = $this->result->error;
    }

    // Follow location.
    if ($this->options('follow_location')) {
      switch ($this->result->code) {
        case 301: // Moved permanently
        case 302: // Moved temporarily
        case 307: // Moved temporarily
        $location = $this->result->headers['location'];
        // Jika location baru hanya path, maka ubah menjadi full url.
        if (preg_match('/^\//',$location)) {
          $parse_url = $this->parse_url;
          $location = $parse_url['scheme'] . '://' . $parse_url['host'] . $location;
        }
        // Get all options.
        $options = $this->options();
        $options['timeout'] -= $this->timer->read() / 1000;
        if ($options['timeout'] <= 0) {
          $this->result->code = -1;
          $this->result->error = 'request timed out';
        }
        elseif ($options['max_redirects']) {
          $options['max_redirects']--;
          // We have changed and must renew options.
          $this->options($options);
          // We must clear cookie that set in header.
          $this->headers('Cookie', NULL);
          // Empty cache filename.
          $this->cache = NULL;
          // And last, we must replace an new URL.
          $this->setUrl($location);
          // Browse again.
          return $this->browse();
        }
      }
    }

    // We must set return so user can playing with parseHTTP object.
    return $this->result;
  }

  protected function _browse() {
    $method = $this->curl ? 'curl_request' : 'drupal_http_request';
    switch ($method) {
      case 'curl_request':
        return $this->{$method}();

      case 'drupal_http_request':
      default:
        return $this->{$method}();
    }
  }

  // Request HTTP modified of function drupal_http_request in Drupal 7.
  // Some part is in this method, another part is in Parse HTTP class.
  protected function drupal_http_request() {
    $result = new parseHTTP;
    $url = $this->getUrl();
    $uri = $this->parse_url;
    $options = $this->options();
    $headers = $this->headers();
    $post = $this->post();

    // Merge the default headers.
    $headers += array(
      'User-Agent' => 'Drupal (+http://drupal.org/)',
    );
    // stream_socket_client() requires timeout to be a float.
    $options['timeout'] = (float) $options['timeout'];

    // Set post.
    if (!empty($post)) {
      // $headers['Content-Type'] = 'multipart/form-data';
      $headers['Content-Type'] = 'application/x-www-form-urlencoded';
      $options['method'] = 'POST';
      // $options['data'] = http_build_query($post);
      $options['data'] = $this->drupal_http_build_query($post);
    }

    // Support proxy.
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
        $headers['Proxy-Authorization'] = 'Basic ' . base64_encode($proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : ''));
      }
      // Some proxies reject requests with any User-Agent headers, while others
      // require a specific one.
      $proxy_user_agent = $this->getState('proxy_user_agent', '');
      // The default value matches neither condition.
      if ($proxy_user_agent === NULL) {
        unset($headers['User-Agent']);
      }
      elseif ($proxy_user_agent) {
        $headers['User-Agent'] = $proxy_user_agent;
      }
    }

    switch ($uri['scheme']) {
      case 'proxy':
        // Make the socket connection to a proxy server.
        $socket = 'tcp://' . $proxy_server . ':' . $this->getState('proxy_port', 8080);
        // The Host header still needs to match the real request.
        $headers['Host'] = $uri['host'];
        $headers['Host'] .= isset($uri['port']) && $uri['port'] != 80 ? ':' . $uri['port'] : '';
        break;
      case 'http':
      case 'feed':
        $port = isset($uri['port']) ? $uri['port'] : 80;
        $socket = 'tcp://' . $uri['host'] . ':' . $port;
        // RFC 2616: "non-standard ports MUST, default ports MAY be included".
        // We don't add the standard port to prevent from breaking rewrite rules
        // checking the host that do not take into account the port number.
        $headers['Host'] = $uri['host'] . ($port != 80 ? ':' . $port : '');
        break;
      case 'https':
        // Note: Only works when PHP is compiled with OpenSSL support.
        $port = isset($uri['port']) ? $uri['port'] : 443;
        $socket = 'ssl://' . $uri['host'] . ':' . $port;
        $headers['Host'] = $uri['host'] . ($port != 443 ? ':' . $port : '');
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
      // $this->setState('drupal_http_request_fails', TRUE);
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
      $headers['Content-Length'] = $content_length;
    }
    // If the server URL has a user then attempt to use basic authentication.
    if (isset($uri['user'])) {
      $headers['Authorization'] = 'Basic ' . base64_encode($uri['user'] . (isset($uri['pass']) ? ':' . $uri['pass'] : ':'));
    }
    //
    $request = $options['method'] . ' ' . $path . " HTTP/1.0\r\n";
    foreach ($headers as $name => $value) {
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
    // echo "\r\n-----------------\r\n";
    // print_r($response);
    // echo "\r\n-----------------\r\n";
    // Drupal code stop here, next we passing to parseHTTP::parse.
    $result->parse($response);
    return $result;
  }

  protected function drupal_http_build_query(array $query, $parent = '') {
    $params = array();

    foreach ($query as $key => $value) {
      $key = ($parent ? $parent . '[' . rawurlencode($key) . ']' : rawurlencode($key));

      // Recurse into children.
      if (is_array($value)) {
        $params[] = drupal_http_build_query($value, $key);
      }
      // If a query parameter value is NULL, only append its key.
      elseif (!isset($value)) {
        $params[] = $key;
      }
      else {
        // For better readability of paths in query strings, we decode slashes.
        $params[] = $key . '=' . str_replace('%2F', '/', rawurlencode($value));
      }
    }

    return implode('&', $params);
  }

  // Request HTTP using curl library.
  // Curl set to not following redirect (location header response)
  // because we must handle set-cookie and save history.
  // Redirect is handle outside curl.
  protected function curl_request() {
    $url = $this->getUrl();
    $uri = $this->parse_url;
    $options = $this->options();
    $headers = $this->headers();
    $post = $this->post();

    // Start curl.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);

    // Set post.
    if (!empty($post)) {
      // Add a new info of headers.
      $headers['Content-Type'] = 'multipart/form-data';
      // $headers['Content-Type'] = 'application/x-www-form-urlencoded';
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }

    // Support proxy.
    $proxy_server = $this->getState('proxy_server', '');
    $proxy_exceptions = $this->getState('proxy_exceptions', array('localhost', '127.0.0.1'));
    $is_host_not_proxy_exceptions = !in_array(strtolower($uri['host']), $proxy_exceptions, TRUE);
    if ($proxy_server && $is_host_not_proxy_exceptions) {
      curl_setopt($ch, CURLOPT_PROXY, $proxy_server);
      $proxy_port = $this->getState('proxy_port', 8080);
      empty($proxy_port) or curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
      if ($proxy_username = $this->getState('proxy_username', '')) {
        $proxy_password = $this->getState('proxy_password', '');
        $auth = $proxy_username . (!empty($proxy_password) ? ":" . $proxy_password : '');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
      }
      // Add a new info of headers.
      $headers['Expect'] = ''; // http://stackoverflow.com/questions/6244578/curl-post-file-behind-a-proxy-returns-error
    }

    // CURL Options.
    foreach ($options as $option => $value) {
      switch ($option) {
        case 'timeout':
          curl_setopt($ch, CURLOPT_TIMEOUT, $value);
          break;

        case 'referer':
          curl_setopt($ch, CURLOPT_REFERER, $value);
          break;

        case 'encoding':
          curl_setopt($ch, CURLOPT_ENCODING, $value);
          break;
      }
    }
    // HTTPS.
    if ($uri['scheme'] == 'https') {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    // Set header.
    $_ = array();
    foreach ($headers as $header => $value) {
      $_[] = $header . ': ' . $value;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $_);
    // Set URL.
    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    // echo "\r\n-----------------\r\n";
    // print_r($response);

    // $parse = preg_split("/\r\n\r\n|\n\n|\r\r/", $response);
    // if (count($parse) > 2) {
      // Kita asumsikan bahwa message HTTP adalah yang berada paling bawah
      // dan header yang paling faktual adalah header sebelumnya.
      // $this->data = array_pop($parse);
      // $response = array_pop($parse);
    // }
    // else {
      // list($response, $this->data) = $parse;
    // }
    // echo '$response';
    // print_r($response);
    // echo '$this->data';
    // print_r($this->data);


    // print_r($info);
    // $result_header = substr($response, 0, $info['header_size']);
    // $result_body = substr($response, $info['header_size']);
    // var_dump($result_header);
    // var_dump($result_body);
    // echo "\r\n-----------------\r\n";
    $error = curl_errno($ch);
    curl_close($ch);
    $result = new parseHTTP;
    // $info is passing by curl.
    if (isset($info['request_header'])) {
      $result->request = $info['request_header'];
    }
    if ($error === 0) {
      $result->parse($response);
    }
    else {
      $result->code = -1;
      switch ($error) {
        case 6:
          $result->error = 'cannot resolve host';
          break;

        case 28:
          $result->error = 'request timed out';
          break;

        default:
          $result->error = 'error occured';
          break;
      }
    }
    return $result;
  }

  // Get random user agent.
  public function getUserAgent($options) {
    // todo:
    if ($options['mobile'] && $options['mobile'] == TRUE) {
      // cari disini.
    }
    return 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_0 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7A341 Safari/528.16';
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

  // Reference of default options.
  private function _default_options() {
    return array(
      'method' => 'GET',
      'data' => NULL,
      'max_redirects' => 3,
      'timeout' => 30.0,
      'context' => NULL,
      'cookie_retrieve' => FALSE,
      'cookie_save' => FALSE,
      'cache_save' => FALSE,
      'history_save' => FALSE,
      'follow_location' => FALSE,
    );
  }
}
