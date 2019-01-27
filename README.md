
## reactphp-curl-proc

Async Curl library for ReactPHP.
This library uses the curl binary of the system.

### Rationale

[HttpClient](https://github.com/reactphp/http-client) and [BuzzBrowser](https://github.com/clue/reactphp-buzz/) are not capable of handling cookies and/or large file uploads.

### Installation

```
composer require choval/reactphp-curl-proc
```

### Usage

```
use choval\React\Curl;
use choval\React\CurlResponse;

$loop = React\EventLoop\Factory::create();
$curl = new Curl($loop);

$curl->get('http://google.com/')
  ->then(function(CurlResponse $resp) {
    echo $resp->getBody();
  })
  ->otherwise(function(Exception $e) {
    echo 'ERROR: '.$e->getMessage().PHP_EOL;
  });
```



