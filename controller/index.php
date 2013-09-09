<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

$app->get('/', function(Request $request) use($app) {
	$lastfmdata = Art\LastFM::request($app['conf'], "chart.getTopArtists", array(
    "limit" => $app['conf']->getOption('app', 'catalogLimit', 100)
  ));

  return $app['view']->render('layout.phtml', 'index/index.phtml', array(
    'artists' => $lastfmdata
  ));
});

$app->get('/about', function() use($app) {
  return $app['view']->render('layout.phtml', 'index/about.phtml');
});

$app->get('/discuss', function() use($app) {
  return $app['view']->render('layout.phtml', 'index/discuss.phtml');
});

$app->get('/contact', function() use($app) { 
  return $app['view']->render('layout.phtml', 'index/contact.phtml');
});

$app->get('/forcopyrighters', function() use($app) { 
  return $app['view']->render('layout.phtml', 'index/forcopyrighters.phtml');
});


