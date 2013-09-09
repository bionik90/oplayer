<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/part/{file}', function($file) use($app) {
	$avparts = array("menu");

	if ( !in_array($file, $avparts) ) {
		return new Response("WTF?");	
	}

  return $app['view']->render(null, "part/{$file}.phtml");
});

$app->get('/cc', function() use($app) {
	$cache = Model\Cache::find('all');

	foreach ( $cache as $c ) {
		$c->delete();
	}

	foreach (glob(ROOT . "/assets/*") as $file) {
		unlink($file);
	}

	return new Response("Cache cleaned");
});

$app->get('/plslist', function() use($app) {
	$pls = Model\Pl::find_all_by_userid( $app['user']::get('id') );

	return $app['view']->render(null, "part/plslist.phtml", array(
		'pls' => $pls
	));
});



