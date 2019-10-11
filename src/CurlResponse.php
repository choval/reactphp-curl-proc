<?php
namespace choval\React;

use React\Stream\ThroughStream;

final class CurlResponse {


  private $stream;
  private $rawHeaders;
  private $rawBody;
  private $rawCookies;
  private $step;

  private $cookies;
  private $headers;
  private $code;

  public function __construct() {
    $this->stream = new ThroughStream;
    $this->step = 'headers';

    $this->rawHeaders = '';
    $this->rawBody = '';
    $this->rawCookies = '';
  }




  /**
   *
   * Writes a chunk
   *
   */
  public function write(string $chunk) {
    switch($this->step) {
      case 'headers':
        $chunk = preg_replace('/^HTTP\/1\.1 100 Continue[\r\n]+/i', '', $chunk);
        $this->rawHeaders .= $chunk;
        $pos = strpos( $this->rawHeaders, "\r\n\r\n");
        if($pos) {
          $this->rawBody = substr($this->rawHeaders, $pos);
          $this->rawHeaders = substr($this->rawHeaders, 0, $pos);
          $this->step = 'body';
        }
        $this->parseHeaders();
        break;
      case 'body':
        $this->rawBody .= $chunk;
        break;
    }
    return $this;
  }




  /**
   *
   * Indicates the end, process the cookies.
   *
   */
  public function end() {
    $pos = strrpos($this->rawBody, '# Netscape HTTP Cookie File');
    if($pos === false) {
      $this->rawCookies = '';
    } else {
      $this->rawCookies = substr($this->rawBody, $pos);
      $this->rawBody = substr($this->rawBody, 0, $pos);
    }
    $this->step = 'end';
  }




  /**
   *
   * Parses cookies
   *
   * Notes:
   *   - Columns: domain tailmatch path secure expires name value
   *
   */
  protected function parseCookies() {
    $this->cookies = [];
    $lines = explode("\n", $this->rawCookies);
    foreach($lines as $line) {
      $line = trim($line);
      if(!$line) {
        continue;
      }
      if(substr($line,0,1) == '#' && substr_count($line,"\t") != 7 ) {
        continue;
      }
      list($domain, $tailmatch, $path, $secure, $expires, $name, $value) = explode("\t", $line, 7);
      $cookie = [
           'domain' => $domain,
        'tailmatch' => $tailmatch,
             'path' => $path,
           'secure' => $secure,
          'expires' => $expires,
             'name' => $name,
            'value' => $value,
      ];
      $this->cookies[] = $cookie;
    }
    return $this->cookies;
  }




  /**
   *
   * Parses headers
   *
   */
  protected function parseHeaders() {
    $headers = explode("\n", $this->rawHeaders);
    $this->headers = [];
    foreach($headers as $header) {
      $header = trim($header);
      if(!$header) {
        continue;
      }
      if(!$this->code && substr($header,0,4) == 'HTTP') {
        $this->code = $header;
      } else {
        $parts = explode(': ', $header, 2);
        $k = $parts[0];
        $v = $parts[1] ?? true;
        $k = implode('-', array_map('ucfirst', explode('-', $k)));
        if(!isset($this->headers[$k])) {
          $this->headers[$k] = $v;
        } else {
          if(!is_array($this->headers[$k])) {
            $this->headers[$k] = [$this->headers[$k]];
          }
          $this->headers[$k] = $v;
        }
      }
    }
    return $this->headers;
  }




  /**
   *
   * Gets headers
   *
   */
  public function getHeaders() {
    return $this->headers;
  }




  /**
   *
   * Gets body
   *
   * Notes:
   *   - It's completely hold in memory
   *
   */
  public function getBody() {
    return $this->rawBody;
  }




  /**
   *
   * Gets cookies
   *
   */
  public function getCookies() {
    if(!$this->cookies) {
      $this->parseCookies();
    }
    return $this->cookies;
  }




  /**
   *
   * Gets raw cookies
   *
   */
  public function getRawCookies() {
    return $this->rawCookies;
  }



}


