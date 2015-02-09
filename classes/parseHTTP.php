<?php
/**
 * @file
 *   parseHTML.php
 *
 * @author
 *   IjorTengab
 *
 * @homepage
 *   https://github.com/ijortengab/classes
 */
class parseHTTP {

  // Property of this class is following the $result object,
  // that define by drupal_http_request().
  var $request;
  var $data;
  var $protocol;
  var $status_message;
  var $headers;
  var $code;
  var $error;

  function __construct($response = NULL) {
    if (isset($response)) {
      $this->parse($response);
    }
  }

  public function parse($response) {
    // Parse response headers from the response body.
    // Be tolerant of malformed HTTP responses that separate header and body with
    // \n\n or \r\r instead of \r\n\r\n.

    // Ada Response yang bentuknya seperti ini:
    /**
     * HTTP/1.1 100 Continue
     *
     * HTTP/1.1 200 OK
     * Date: Mon, 26 Jan 2015 02:40:50 GMT
     * Server: Apache/2.4.3 (Win32) OpenSSL/1.0.1c PHP/5.4.7
     * X-Powered-By: PHP/5.4.7
     * Content-Length: 5
     * Content-Type: text/html
     *
     * <!DOCTYPE><html><head><title></title></head><body></body></html>
     */
    // Sehingga perlu diantisipasi.
    list($header, $data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
    // Antisipasi kasus diatas.
    if (strpos($data, 'HTTP') === 0) {
      list($header, $data) = preg_split("/\r\n\r\n|\n\n|\r\r/", $data, 2);  
    }
    $this->data = $data;   
    $response = preg_split("/\r\n|\n|\r/", $header);
    // Parse the response status line.
    list($protocol, $code, $status_message) = explode(' ', trim(array_shift($response)), 3);
    $this->protocol = $protocol;
    $this->status_message = $status_message;

    $this->headers = array();
    $this->headers_raw = $response;
    // Parse the response headers.
    while ($line = trim(array_shift($response))) {
      list($name, $value) = explode(':', $line, 2);
      $name = strtolower($name);
      if (isset($this->headers[$name]) && $name == 'set-cookie') {
        // RFC 2109: the Set-Cookie response header comprises the token Set-
        // Cookie:, followed by a comma-separated list of one or more cookies.
        $this->headers[$name] .= ',' . trim($value);
      }
      else {
        $this->headers[$name] = trim($value);
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
    $this->code = $code;
  }

  public function parse_curl() {

  }
}
