<?php
/*
 * @require simple_html_dom
 */
namespace Project;

class OpenPlayer {
  public static $app = null;
  public $account = null;
  public $access_token = null;

  public function __construct( $accounts, $appIds ) {
    $this->account = $accounts[
      array_rand($accounts)
    ];

    $this->appId = $appIds[
      array_rand($appIds)
    ];
  }

  public function getToken( $retoken = false ) {
    $account = $this->account;
    $self = $this;
    $token = Cache::get("token_".sha1($account), 60*60*24*7, function() use ( $account, $self ) {
      $token = null;
      $cookiepath = __DIR__."/../../cache/cookie_".sha1($account);

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "http://m.vk.com/login");
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
      curl_setopt($ch, CURLOPT_TIMEOUT, 20);
      $resp = $self->curl_redirect_exec($ch);
      curl_close($ch);
// print_r($resp);die;
      $html = str_get_html($resp);
      if ( $html ) {
        // echo 'wtf';die;
        $form = $html->find('form', -1);
        if ( $action = $form->action ) {
          $accountInfo = explode(':', $account, 3);
          $params = array(
            'email' => $accountInfo[0],
            'pass' => $accountInfo[1]
          );

          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $action);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
          curl_setopt($ch, CURLOPT_HEADER, 0);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
          curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
          curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
          curl_setopt($ch, CURLOPT_TIMEOUT, 20);
          $resp = $self->curl_redirect_exec($ch);
          curl_close($ch);

          // Проверка номера телефона
          if ( false !== strpos($resp, 'security_check') && isset($accountInfo[2]) ) {
            preg_match_all("/hash: \'([\w\d]+)\'/", $resp, $matches);

            if ( $hash = $matches[1][0] ) {
              $params = array(
                '_ajax' => '1',
                'code' => substr($accountInfo[2], 2, -2),
              );

              $ch = curl_init();
              $curl_header = array(
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer' => 'http://m.vk.com/login.php?act=security_check&to=&al_page='
              );
              curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
              curl_setopt($ch, CURLOPT_URL, "http://m.vk.com/login.php?act=security_check&to=&hash={$hash}");
              curl_setopt($ch, CURLOPT_POST, true);
              curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
              curl_setopt($ch, CURLOPT_HEADER, 1);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
              curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
              curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
              curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
              curl_setopt($ch, CURLOPT_TIMEOUT, 20);

              $resp = $self->curl_redirect_exec($ch);
              curl_close($ch);
            }
          }

          if ( false !== strpos($resp, 'service_msg_warning') ) {
            header("Location:/vkerror");
            die;
          }
        }
      }
      
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_NOBODY, 0);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
      curl_setopt($ch, CURLOPT_URL, 'http://oauth.vk.com/authorize?client_id='.$self->appId.'&scope=audio&response_type=token');
      $resp = $self->curl_redirect_exec($ch);
      curl_close($ch);
// print_r($resp);die;
      // Here I recieve token, or form to grant access to application, so
      $html = str_get_html($resp);
      if ( $html ) {
        $form = $html->find('form', -1);
        if ( $form && $action = $form->action ) {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $action);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, '');
          curl_setopt($ch, CURLOPT_HEADER, 0);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
          curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
          curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
          curl_setopt($ch, CURLOPT_TIMEOUT, 20);
          $resp = curl_exec($ch);
          curl_close($ch);
// print_r($resp);die;
          $token = $self->getToken();
        }
      }

      return $self->access_token;
    }, $retoken);

    return $token;
  }

  public function audioGetById($vkId, $reget = false) {
    $params = array(
      'api_id' => $this->appId,
      'v' => '3.0',
      'method' => 'audio.getById',
      'format' => 'json',
      'test_mode' => 1,
      'audios' => $vkId
    );
    $http_query = http_build_query($params);
    $token = $this->getToken();
    $result = $this->file_get_contents_curl("https://api.vk.com/method/audio.getById?{$http_query}&access_token={$token}");
    $result = json_decode($result);

    if ( !$reget && isset($result->error) && 5 == $result->error->error_code ) {
      $token = $this->getToken(true);
      return $this->audioGetById($vkId, true);
    }elseif ( $reget && isset($result->error) && 5 == $result->error->error_code ) {
      header("Location:/vkerror");
      die;
    }

    $result = $result->response;

    $track = $result[0];

    $track->fname = str_replace(" ", "_", "{$track->artist} — {$track->title}.mp3");
    $track->size = $this->remoteFilesize($track->url);

    return $track;
  }

  public function audioGetLyrics( $lyricsId ) {
    $params = array(
      'api_id' => $this->appId,
      'v' => '3.0',
      'method' => 'audio.getLyrics',
      'format' => 'json',
      'test_mode' => 1,
      'lyrics_id' => $lyricsId
    );
    $http_query = http_build_query($params);
    $token = $this->getToken();
    $result = $this->file_get_contents_curl("https://api.vk.com/method/audio.getById?{$http_query}&access_token={$token}");
    $result = json_decode($result);

    $result = $result->response;

    return $result->text;
  }

  public function remoteFilesize($url) {
    $head = get_headers($url, 1);
    return isset($head['Content-Length']) ? $head['Content-Length'] : "unknown";
  }

  public function search( $q, $p = 0, $count = 200, $captcha_sid = null, $captcha_key = null, $research = false ) {
    $cookiepath = __DIR__."/../../cache/cookie_".sha1($this->account);

    // Check for auth
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://m.vk.com/');
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = $this->curl_redirect_exec($ch);
    curl_close($ch);

    if ( strpos($resp, 'login.vk.com/?act=login') ) {
      $token = $this->getToken(true);
    }

    if ( !$p || $p <= 0 ) $p = 0;
    else $p *= $count;

    $token = $this->getToken();
    
    $params = array(
      'act' => 'search',
      'al' => '1',
      'gid' => '0',
      // 'id' => '5954696',
      'offset' => $p,
      'performer' => '1',
      'q' => $q,
      'sort' => '0',
      'count' => $count
    );

    if ( $captcha_sid && $captcha_key ) {
      $params['captcha_sid'] = $captcha_sid;
      $params['captcha_key'] = $captcha_key;
    }

    $http_query = http_build_query($params);
    $token = $this->getToken();
    $result = $this->file_get_contents_curl("https://api.vk.com/method/audio.search?{$http_query}&access_token={$token}");
    $result = json_decode($result);

    // If captcha
    if ( isset($result->error) && 14 == $result->error->error_code ) {
      header("Location:/captcha?img={$result->error->captcha_img}&sid={$result->error->captcha_sid}");
      die;
    }

    if ( !$research && isset($result->error) && 5 == $result->error->error_code ) {
      $token = $this->getToken(true);
      return $this-> search( $q, $p, $count, $captcha_sid, $captcha_key, true );
    }elseif ( $research && isset($result->error) && 5 == $result->error->error_code ) {
      header("Location:/vkerror");
      die;
    }

    $result = $result->response;

    $count = $result[0];

    $tracks = array();
    foreach ( $result as $key => $track ) {
      if ( 0 == $key ) continue;

      $track = array(
        'vkId' => "{$track->owner_id}_{$track->aid}",
        'url' => $track->url,
        'duration' => gmdate("i:s", $track->duration),
        'artist' => $track->artist,
        'title' => $track->title,
      );

      $tracks[] = $track; 
    }


//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, 'http://vk.com/audio');
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
//     curl_setopt($ch, CURLOPT_HEADER, 0);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//     curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
//     curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiepath);
//     curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiepath);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
//     $resp = $this->curl_redirect_exec($ch);
//     curl_close($ch);
// // print_r($resp);die;
//     $resp = mb_convert_encoding($resp, "UTF-8", "windows-1251");

//     preg_match_all("/<!--(\d+)<!>/", $resp, $matches);
//     $count = isset($matches[1])
//       ? isset($matches[1][0])
//         ? $matches[1][0]
//         : 0
//       : 0;

//     $tracks = array();
//     $html = str_get_html($resp);
//     if ( $html ) {
//       $audios = $html->find('.audio');

//       foreach ( $audios as $audio ) {
//         $meta = $audio->find('input[type=hidden]', 0)->value;
//         $meta = explode(',', $meta);

//         $duration = gmdate("i:s", $meta[1]);

//         $track = array(
//           'vkId' => $audio->find('a', 0)->name,
//           'url' => $meta[0],
//           'duration' => $duration,
//           'artist' => trim(preg_replace('/\s+/', ' ', $audio->find('.title_wrap b', 0)->plaintext)),
//           'title' => trim(preg_replace('/\s+/', ' ', $audio->find('.title_wrap .title', 0)->plaintext)),
//         );

//         $tracks[] = Cache::get("track_{$track['vkId']}", 60*60*24*7, function() use ($track) {
//           return $track;
//         });
//       }
//     }

    return array(
      'count' => $count,
      'tracks' => $tracks
    );
  }

  public function file_get_contents_curl($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);       

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
  }

  public function curl_redirect_exec($ch, &$redirects = 0, $curloptHeader = false) {
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ( in_array($httpCode, array(301, 302)) ) {
      list($header) = explode("\r\n\r\n", $data, 2);
      $matches = array();

      //this part has been changed from the original
      preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
      $url = trim( str_replace($matches[1], "", $matches[0]) );
      
      if ( false === strpos($url, 'http') ) { $url = "http://vk.com{$url}"; }

      if ( preg_match_all('/access_token=(.*)&expires_in=86400/i', $url, $matches) ) {
        $this->access_token = $matches[1][0];
        // Cache::set('access_token', $matches[1][0], 60*60);
      }
      //end changes

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
