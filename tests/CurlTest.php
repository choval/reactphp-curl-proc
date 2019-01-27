<?php
declare(strict_types=1);

use React\EventLoop\Factory;
use PHPUnit\Framework\TestCase;
use choval\React\Curl;
use Clue\React\Block;

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




}


