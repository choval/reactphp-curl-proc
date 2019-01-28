<?php
declare(strict_types=1);

use React\EventLoop\Factory;
use PHPUnit\Framework\TestCase;
use choval\React\Curl;
use Clue\React\Block;
use React\Stream\ThroughStream;

final class CurlTest extends TestCase {

  static $loop;
  static $curl;

  public static function setUpBeforeClass() {
    static::$loop = Factory::create();
    static::$loop->run();
    static::$curl = new Curl( static::$loop );
  }


  public function dataProvider() {
    $rand = sha1(microtime(true).rand());
    return [
      [['random'=>$rand]],
    ];
  }



  /**
   * @dataProvider dataProvider
   */
  public function testGet($data) {
    $uri = 'http://httpbin.org/get';
    $result = Block\await( static::$curl->get($uri, $data), static::$loop);
    $body = $result->getBody();
    $res = json_decode($body, true);
    $this->assertEquals( $data, $res['args'] );
  }




  /**
   * @dataProvider dataProvider
   */
  public function testPost($data) {
    $uri = 'http://httpbin.org/post';
    $result = Block\await( static::$curl->post($uri, $data), static::$loop);
    $body = $result->getBody();
    $res = json_decode($body, true);
    $out = [];
    parse_str($res['data'], $out);
    $this->assertEquals( $data, $out );
  }




  /**
   * @dataProvider dataProvider
   * @depends testPost
   */
  public function testPostStream($data) {
    $uri = 'http://httpbin.org/post';
    $stream = new ThroughStream;
    $prom = static::$curl->request('POST', $uri, $stream);
    $stream->write(http_build_query($data));
    $stream->end();
    $result = Block\await( $prom, static::$loop);
    $body = $result->getBody();
    $res = json_decode($body, true);
    $this->assertEquals( $data, $res['form'] );
  }

   

}


