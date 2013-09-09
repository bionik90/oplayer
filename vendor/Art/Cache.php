<?php
namespace Project;

class Cache {
  public static function get( $key, $expired, $getFunction, $recache = false ) {
    $cache = \Model\Cache::find(array(
      'conditions' => array(
        'cache.key = ? AND cache.expiredAt > NOW() - INTERVAL ? SECOND',
        $key, $expired
      ),
      'order' => 'id DESC'
    ));

    if ( !$cache || $recache ) {
      $value = $getFunction();

      $cache = new \Model\Cache;
      if ( $value ) {
        $cache->key = $key;
        $cache->data = serialize($value);
        $cache->expiredat = new \DateTime(date('Y-m-d H:i:s', time() + $expired));
        $cache->save();
      }
    }

    $value = $cache->data;
    if ( $value ) {
      return unserialize($value);
    }

    return null;
  } 
}