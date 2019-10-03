<?php
namespace choval\React;

use React\Promise\Deferred;
use React\Promise;

use React\ChildProcess\Process;
use React\Stream\ReadableStreamInterface;


final class Curl {

  private $loop;

  private $cookies;
  private $userAgent;
  private $followRedirects;

  private $timeOut = 60;
  private $since;
  private $timer;


  /**
   *
   * Constructor
   *
   */
  public function __construct($loop) {
    $this->loop = $loop;
    $this->cookies = [];
  }




  /**
   *
   * Get
   *
   */
  public function get($uri, array $args=[], array $headers=[]) {
    $uriParts = parse_url($uri);
    $query = [];
    if(isset($uriParts['query'])) {
      parse_str($uriParts['query'], $query);
    }
    $query = array_merge($query, $args);
    if(!empty($query)) {
      $uriParts['query'] = http_build_query($query);
    }
    $uri = static::glue_url($uriParts);
    return $this->request('GET', $uri);
  }




  /**
   *
   * Post
   *
   */
  public function post($uri, $body, array $headers=[]) {
    if(is_array($body)) {
      if(preg_match('/content-type:[^;\/]+\/json/i', implode(';',$headers))) {
        $body = json_encode($body);
      } else {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $body = http_build_query($body);
      }
    }
    /*
    if(strlen($body) < 511) {
      if(is_file($body)) {
        // TODO: From file
      }
    }
    */
    return $this->request('POST', $uri, $body, $headers);
  }




  // http://php.net/manual/es/function.parse-url.php#121718
  private static function glue_url($parsed) {
    if (!is_array($parsed)) {
        return false;
    }
    $uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '' : '//') : '';
    $uri .= isset($parsed['user']) ? $parsed['user'].(isset($parsed['pass']) ? ':'.$parsed['pass'] : '').'@' : '';
    $uri .= isset($parsed['host']) ? $parsed['host'] : '';
    $uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';
    if (isset($parsed['path'])) {
        $uri .= (substr($parsed['path'], 0, 1) == '/') ?
            $parsed['path'] : ((!empty($uri) ? '/' : '' ) . $parsed['path']);
    }
    $uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
    $uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';
    return $uri;
  }




  /**
   *
   * Normalize headers
   *
   */
  public function normalizeHeaders(array $headers) {
    $normalized = []; 
    foreach($headers as $k=>$v) {
      if(is_numeric($k)) {
        $parts = explode(':', $v, 2);
        $k = $parts[0];
        $v = $parts[1] ?? null;
        $normalized[] = $k.(is_null($v) ? '' : ': '.$v);
      }
      $k = implode('-', array_map('ucfirst', explode('-', $k)));
      if(is_array($v)) {
        foreach($v as $subv) {
          $normalized[] = "$k: $subv";
        }
      } else {
        $normalized[] = $k.(is_null($v) ? '' : ': '.$v);
      }
    }
    if(empty($normalized)) {
      return '';
    }
    return ' --header "'.implode(';', $normalized).'"';
  }




  /**
   *
   * Normalize cookies
   *
   * Notes:
   *   - Expects keypair of key/values.
   *
   */
  public function normalizeCookies(array $cookies) {
    $values = [];
    foreach($cookies as $k=>$v) {
      $values[] = "$k=$v";
    }
    if(empty($values)) {
      return '';
    }
    return ' --cookie '.implode(';',$cookies).' ';
  }




  /**
   *
   * Get cookies
   *
   * Notes:
   *    cookie.c
   *    domain tailmatch path secure expires name value
   *
   */
  public function getCookies(string $url) {
    $parts = parse_url($url);
    $keypairs = [];
    foreach($this->cookies as $pos=>$cookie) {
      // Domain
      if($cookie['domain'] != $parts['host']) {
        if(empty($cookie['tailmatch'])) {
          continue;
        } else {
          if(strrpos($parts['host'], $cookie['domain']) === false) {
            continue;
          }
        }
      }
      // Secure
      if(empty($cookie['secure'])) {
        if($parts['scheme'] != 'http') {
          continue;
        }
      } else {
        if($parts['scheme'] != 'https') {
          continue;
        }
      }
      // Path
      if(strpos($parts['path'], $cookie['path']) !== 0) {
        continue;
      }
      // Expires
      if($cookie['expires'] !== '0') {
        $time = strtotime($cookie['expires']);
        if($time < time()) {
          unset($this->cookies[$pos]);
          continue;
        }
      }
      // Value
      if(strlen($cookie['value']) === 0) {
        unset($this->cookies[$pos]);
        continue;
      }
      // Add to keypairs
      $keypairs[ $cookie['name'] ] = $cookie['value'];
    }
    return $keypairs;
  }




  /**
   *
   * Make a request
   *
   */
  public function request(string $mode, string $url, $body=null, array $headers=[]) {
    $defer = new Deferred;
    $modes = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS' ];  // Other methods not supported
    if(!in_array($mode, $modes)) {
      throw new \Exception('MODE NOT SUPPORTED');
    }
    $hasBody = is_null($body) ? false : true;
    $bodyString = '';
    if($hasBody) {
      if(is_array($body)) {
        $bodyString = '';
        foreach($body as $k=>$v) {
          $bodyString .= ' -F "'.$k.'='.$v.'" ';
        }
        $body = '';
      } else {
        $bodyString = '-d @-';
      }
    }
    $headersString = $this->normalizeHeaders($headers);

    $followString = '';
    $postString = '';
    $cookieJarString = ' --cookie-jar - ';

    if($this->followRedirects) {
      $followString = ' -L ';
      /*
      // -- There's a bug in curl not passing cookies when following redirects
      $file = tempnam(sys_get_temp_dir(), 'curl');
      $cookieJarString = ' --cookie-jar "'.$file.'" ';
      $postString = ' && cat "'.$file.'" && rm "'.$file.'" ';
      */
    }

    $cookies = $this->getCookies($url);
    $cookiesString = $this->normalizeCookies($cookies);

    $userAgent = empty($this->userAgent) ? '' : ' -A "'.$this->userAgent.'"'; // TODO: Prevent injection
    $cmd = 'curl '.$userAgent.' '.$followString.' '.$cookiesString.' --include '.$cookieJarString.' -X '.$mode.' '.$headersString.' '.$bodyString.' "'.$url.'" '.$postString;
    $response = new CurlResponse();
    $process = new Process($cmd);
    $process->start( $this->loop );
    $this->since = time();
    $process->stdout->on('data', function ($chunk) use ($response) {
      $response->write($chunk);
    });
    $process->on('exit', function ($code) use ($response, $defer) {
      $response->end();
      $cookies = $response->getCookies();
      $this->setCookies($cookies);
      if($this->timer) {
        $this->loop->cancelTimer($this->timer);
      }
      $defer->resolve($response);
    });
    if($hasBody) {
      $this->loop->addTimer(1, function() use ($body, $process) {
        if($body instanceof ReadableStreamInterface) {
          $body->on('data', function($chunk) use ($process) {
            $process->stdin->write($chunk);
          });
          $body->on('end', function() use ($process) {
            // Needs a double end for stdin
            $process->stdin->end();
            // $process->stdin->end();
          });
        } else {
          // $process->stdin->write($body);
          $process->stdin->write($body);
          $process->stdin->end();
          $this->stdInEnd = true;
        }
      });
      $this->timer = $this->loop->addPeriodicTimer(0.1, function($timer) use ($process) {
        /*
        if($this->stdInEnd) {
          $process->stdin->end();
        }
        */
        if(($this->since + $this->timeOut) < time()) {
          $this->loop->cancelTimer($timer);
          $process->terminate();
        }
      });
    }
    return $defer->promise();
  }
  private $stdInEnd = false;




  /**
   *
   * Unsets cookies and any other stored data.
   *
   */
  public function reset() {
    $this->cookies = null;
    return $this;
  }




  /**
   *
   * Sets cookies
   *
   * Notes:
   *    cookie.c
   *    domain tailmatch path secure expires name value
   *
   */
  public function setCookies(array $cookies) {
    foreach($cookies as $cookie) {
      $this->cookies[] = $cookie;
    }
    return $this;
  }




  /**
   *
   * Sets the user agent
   *
   */
  public function setUserAgent(string $userAgent) {
    $this->userAgent = $userAgent;
    return $this;
  }



  /**
   *
   * Follow redirects
   *
   */
  public function setFollowRedirects(bool $follow) {
    $this->followRedirects = $follow;
    return $this;
  }

}


