<?php

$app['view'] = function () use($app) {
    return new Art\View($app);
};

$app['conf'] = function () {
    return Art\Config::getInstance();
};

$app['user'] = function() {
    return new Art\User;
};

class Reg {
    private static $reg = array();

    public static function set( $key, $value ) {
        self::$reg[$key] = $value;
    }

    public static function get( $key ) {
        return self::$reg[$key];
    }
}
Reg::set('app', $app);
Reg::set('seo', $app['conf']->getOptions('seo'));


require_once ROOT . '/vendor/AR/ActiveRecord.php';
ActiveRecord\Config::initialize(function($cfg) use ($app) {
    $cfg->set_model_directory( ROOT . '/model');
    $cfg->set_connections(array(
        'production' => $app['conf']->getOption('db', 'dsn')
    ));

    $cfg->set_default_connection('production');
});

require_once ROOT . '/vendor/Art/OpenPlayer.php';
$vkconf = $app['conf']->getOptions('vk');
$app['openplayer'] = new OpenPlayer\Core(
    $vkconf['appId'], 
    $vkconf['userId']
);