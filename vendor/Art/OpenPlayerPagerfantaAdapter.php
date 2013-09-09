<?php
namespace Art;

class OpenPlayerPagerfantaAdapter implements \Pagerfanta\Adapter\AdapterInterface {
	private $data = array();

	public function search() {
		if ( !count( $this->data) ) {
      $q = $this->q;
      $p = $this->p;
      $ipp = $this->ipp;
      $openplayer = $this->openplayer;

      $data = \Project\Cache::get("vk_search_{$q}_p_{$p}_ipp_{$ipp}", 60*60*24*7, function() use ($openplayer, $q, $p, $ipp) {
        $data = $openplayer->search( 
          $this->q, $this->p, $this->ipp
        );

        if ( $data['tracks'] ) {
          return $data;
        }

        return null;
      });

      $this->data = array(
        'count' => $data['count'],
        'result' => $data['tracks'],
      );

      // print_r($this->data);die;
		}

		return $this->data;
	}

  public function __construct( $openplayer, $q, $p, $ipp ) {
  	$this->openplayer = $openplayer;
  	$this->q = $q;
  	$this->p = $p - 1;
  	$this->ipp = $ipp;
  }

  public function getNbResults() {
    $this->res = $this->search();

    $cnt = $this->res['count'] > 1000 ? 1000 : $this->res['count'];
    return $cnt;
  }

  public function getSlice($offset, $length) {
  	$this->res = $this->search();
    return $this->res['result'];
  }
}