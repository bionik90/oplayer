<?php
die('This file doesn`t work anymore, use OpenPlayer3.php instead. This file is saved for history.');
namespace OpenPlayer;

// file caching
class Cache {
  public static $cacheRoot = "/assets";

  public static function set($key, $value, $time = 60) {
    $data = new \Model\Cache;
    $data->key = $key;
    $data->data = $value;
    $data->expiredat = date("Y-m-d H:i:s", time() + $time);

    $data->save();
  }

  public static function get($key) {
    $data = \Model\Cache::find('one', array('conditions' => array(
      '`expiredAt` > ? AND `key` = ?', date("Y-m-d H:i:s"), $key
    )));

    return $data ? $data->data : null;
  }

  public static function clear($key) {
    $cache = \Model\Cache::find('one', array('conditions' => array(
      '`expiredAt` > ? AND `key` = ?', date("Y-m-d H:i:s"), $key
    )));

    return $cache ? $cache->delete() : false;
  }

  public static function clearAll() {
    $caches = \Model\Cache::all();
    foreach ($caches as $cache) {
      $cache->delete();
    }
  }
}

class Core {
  private $userId;
  private $appId;

  public function __construct($appId, $userId) {
    $this->appId = $appId;
    $this->userId = $userId;
  }

  public function audioSearch( $q, $page = 0, $count = 10, $cacheTime = 86400 ) {
    $q = str_replace("&", " ", $q);

    if ( !$page || $page <= 0 ) {
      $page = 0;
    } else {
      $page *= $count;
    }
        
    $cachekey = "vkontakte_" . sha1($q . $page . $count) . ".xml";
    $result = Cache::get($cachekey);
    if ( !$result ) {
      $params = array(
        'api_id' => $this->appId,
        'v' => '5.0',
        'method' => 'audio.search',
        'count' => $count,
        'offset' => $page,
        'scope' => 'audio',
        'q' => $q,
        'format' => 'json',
        'test_mode' => 1
      );

      ksort($params);
      $http_query = http_build_query($params);

      $tsig = $this->userId . str_replace( '&', '', urldecode($http_query) );
      $sig = md5($tsig);

      $result = file_get_contents("https://api.vk.com/api.php?" . $http_query . "&sig=" . $sig);
      // echo "http://api.vk.com/api.php?" . $http_query . "&sig=" . $sig;die;
      print_r($result);die;
      Cache::set($cachekey, $result, $cacheTime);
    }

    $result = json_decode($result);
    if ( isset($result->response) ) {
      $result = $result->response;
      $count = $result[0];
      unset($result[0]);

      foreach ($result as $key => $value) {
        $result[$key] = (array) $value;
      }
    } else {
      $result = array();
      $count = 0;
    }

    return array(
      'count' => $count,
      'result' => $result
    );
  }

  public function audioGetById($vkId) {
    $params = array(
      'api_id' => $this->appId,
      'v' => '3.0',
      'method' => 'audio.getById',
      'format' => 'json',
      'test_mode' => 1,
      'audios' => $vkId
    );
    ksort($params);
    $http_query = http_build_query($params);

    $tsig = $this->userId . str_replace('&', '', $http_query);
    $sig = md5($tsig);

    $result = file_get_contents("http://api.vk.com/api.php?" . $http_query . "&sig=" . $sig);
    $result = json_decode($result);
    $result = $result->response;
            
    return $result[0];
  }

  public function getTrack($vkId) {
    $song = $this->audioGetById($vkId);
    $url = $song->url;

    return array(
      'url' => $url,
      'fname' => str_replace(" ", "_", "{$song->artist} — {$song->title}.mp3"),
      'size' => $this->remoteFilesize($url)
    );
  }

  public function audioGetLyrics($id) {
    $params = array(
      'api_id' => $this->appId,
      'v' => '3.0',
      'method' => 'audio.getLyrics',
      'format' => 'json',
      'test_mode' => 1,
      'lyrics_id' => $id
    );

    ksort($params);
    $http_query = http_build_query($params);

    $tsig = $this->userId . str_replace('&', '', $http_query);
    $sig = md5($tsig);

    $result = file_get_contents("http://api.vkontakte.ru/api.php?" . $http_query . "&sig=" . $sig);
    $result = json_decode($result);
    $result = $result->response;

    return $result->text;
  }

  function remoteFilesize($url) {
    $head = get_headers($url, 1);
    return isset($head['Content-Length']) ? $head['Content-Length'] : "unknown";
  }

  public function curl_redirect_exec($ch, &$redirects = 0, $curloptHeader = false) {
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (in_array($httpCode, array(301, 302))) {
      list($header) = explode("\r\n\r\n", $data, 2);
      $matches = array();

      preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
      $url = trim(str_replace($matches[1], "", $matches[0]));

      // if (preg_match_all('/access_token=(.*)&expires_in=86400/i', $url, $matches)) {
      //   Cache::set('access_token', $matches[1][0], 60 * 60);
      // }

      $urlParsed = parse_url($url);
      if ( isset($urlParsed) ) {
        curl_setopt($ch, CURLOPT_URL, $url);
        $redirects++;
        return $this->curl_redirect_exec($ch, $redirects);
      }
    }

    if ( $curloptHeader ) {
      return $data;
    } else {
      $ttt = explode("\r\n\r\n", $data, 2);
      return isset($ttt[1]) ? $ttt[1] : null;
    }
  }

}
