<?php
// РЕКЛАМА!!!!!!!!!!!!!! + ДОНЕЙТ!!!!!!!!!!!!!!!1

// Powered by:
//   - Silex (http://silex.sensiolabs.org), 
//   - PHPActiveRecord (http://phpactiverecord.org), 
//   - OpenPlayer (https://github.com/uavn/openplayer), 
//   - Pagerfanta (https://github.com/whiteoctober/Pagerfanta)

session_start();
date_default_timezone_set('Europe/Moscow');

define('ROOT', __DIR__);
require_once ROOT . '/silex.phar';
require_once ROOT . '/autoload.php';
require_once ROOT . '/helpers.php';
require_once 'phar://'.__DIR__.'/silex.phar/autoload.php';

$app = new Silex\Application();

$app['debug'] = 'localhost' == $_SERVER['HTTP_HOST'];
ini_set("display_errors", $app['debug'] ? "on" : "off");
 $app['debug'] = 1;//@TODO remove

require_once ROOT . '/services.php';
require_once ROOT . '/controllers.php';

$app->run();


// clearing old cache
$cache = Model\Cache::find('all', array('conditions' => array('expiredAt < ?', date("Y-m-d H:i:s"))));
foreach ( $cache as $c ) $c->delete();