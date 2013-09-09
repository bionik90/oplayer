<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pagerfanta\Pagerfanta;
use Pagerfanta\View\DefaultView;

$app->get('/vkerror', function(Request $request) use ( $app ) {
  if( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) ) {
    return "<div><div class='subcontent'><script>location.href = '/vkerror';</script></div></div>";
  }

  return $app['view']->render('layout.phtml', 'search/vkerror.phtml', array(
  ));
});

$app->get('/captcha', function(Request $request) use($app) {
  $img = $request->get('img');
  $sid = $request->get('sid');

  return $app['view']->render('layout.phtml', 'search/captcha.phtml', array(
    'img' => $img,
    'sid' => $sid
  ));
});

$app->post('/entercaptcha', function(Request $request) use($app) {
  $sid = $request->get('sid');
  $key = $request->get('key');

  $app['openplayer']->search(
    'Test', 0, 1,
    $sid, $key
  );

  return $app->redirect('/');
});

$app->get('/search/{query}', function(Request $request, $query) use($app) {
  $q = $app->escape($query);
  $q = html_entity_decode($q);

  $seo = Reg::get('seo');
  $seo['title'] = "Слушать {$q} онлайн.";
  Reg::set('seo', $seo);

  $p = $request->get('p', 1);
  $ipp = $app['conf']->getOption('app', 'itemsPerPage', 10) ;
  $pagerfanta = new Pagerfanta(new Art\OpenPlayerPagerfantaAdapter( 
    $app['openplayer'], str_replace("&", " ", $q), $p, $ipp
  ));
  $pagerfanta->setCurrentPage($p);
  $pagerfanta->setMaxPerPage($ipp);

  $view = new DefaultView();
  $pagination = $view->render($pagerfanta, function($page) use ($q) { 
      return "./search/{$q}?p={$page}";
    }, array(
      'proximity' => 5,
      'next_message' => 'Вперед',
      'previous_message' => 'Назад',
    )
  );

  $similar = array();
  if ( substr_count($q, " ") < 2 ) {
    $lastfmdata = Art\LastFM::request($app['conf'], "artist.getSimilar", array(
      "limit" => 10,
      "artist" => $q
    ));

    if ( isset($lastfmdata->similarartists) ) {
      $similar = $lastfmdata->similarartists->artist;
    }
  }
    
  Reg::set('q', $q);

  return $app['view']->render('layout.phtml', 'search/list.phtml', array(
    'res' => $pagerfanta,
    'pagination' => $pagination,
    'q' => $q,
    'similar' => $similar
  ));
});

$app->get('/track/{vkid}', function(Request $request, $vkid) use($app) {
  $vtrack = \Project\Cache::get("vk_track_{$vkid}", 60*60*24*14, function() use ( $app, $vkid ) {
    return $app['openplayer']->audioGetById( $vkid );
  });

  $seo = Reg::get('seo');
  $seo['title'] = "Слушать {$vtrack->artist} - {$vtrack->title} онлайн.";
  Reg::set('seo', $seo);

  $track = (array)$vtrack;
  $track['vkId'] = "{$vtrack->owner_id}_{$vtrack->aid}";
  $track['duration'] = gmdate("i:s", $vtrack->duration);

  return $app['view']->render('layout.phtml', 'part/track.phtml', array(
    'track' => $track
  ));
});

$app->get('/getlyrics', function(Request $request) use($app) { 
  $text = nl2br($app['openplayer']->audioGetLyrics( 
    $request->get('lyricsId')
  ));

  return new Response($text);
});

$app->get('/getsong/{vkid}', function(Request $request, $vkid) use($app) {
  session_write_close();

  $vkTrack = \Project\Cache::get("vk_track_{$vkid}", 60*60*24*7, function() use ( $app, $vkid ) {
    return $app['openplayer']->audioGetById( $vkid );
  });

  // If cached url is expired, recache track.
  $headers = get_headers($vkTrack->url);
  if ( 'HTTP/1.1 200 OK' != $headers[0] ) {
    $vkTrack = \Project\Cache::get("vk_track_{$vkid}", 60*60*24*7, function() use ( $app, $vkid) {
      return $app['openplayer']->audioGetById( $vkid );
    }, true);
  }

  $vkTrack = (array)$vkTrack;
  header("Content-Length: {$vkTrack['size']}");

  if ( $request->get('dl') ) {
    header('Last-Modified:');
    header('ETag:');
    header('Content-Type: audio/mpeg');
    header('Accept-Ranges: bytes');

    header("Content-Disposition: attachment; filename=\"{$vkTrack['fname']}\"");
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
  }

  return $app->stream(function () use ($vkTrack) {
    readfile($vkTrack['url']);
  }, 200, array('Content-Type' => 'audio/mpeg'));
});