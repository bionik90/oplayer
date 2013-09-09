<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->get('/login', function(Request $request) use($app) {
	return $app['view']->render("layout.phtml", "login.phtml", array());
});

$app->post('/user/plspos', function(Request $request) use($app) {
	$poss = $request->get('poss');

	$pls = Model\Pl::find_all_by_id_and_userid($poss, $app['user']::get('id'));
	
	if ( $pls ) {
		foreach ( $pls as $pl ) {
			$pl->pos = array_search($pl->id, $poss);
			$pl->save();
		}
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/pltrackspos', function(Request $request) use($app) {
	$playlistId = $request->get('playlistId');
	$poss = $request->get('poss');

	$pl = Model\Pl::find_by_id_and_userid($playlistId, $app['user']::get('id'));
	
	if ( $pl ) {
		$plts = Model\PlTrack::find_all_by_vkid_and_plid($poss, $playlistId);
		if ( $plts ) {
			foreach ( $plts as $plt ) {
				$plt->pos = array_search($plt->vkid, $poss);
				$plt->save();
			}
		}
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/login', function(Request $request) use($app) {
	$login = $request->get('login'); 
	$password = $request->get('password');
	$password = $password ? md5($password) : null;

	$status = false;
	if ( trim($login) ) {
		$user = Model\User::find_by_login_and_password(
			$login, $password	
		);

		if ( !$user ) {
			$user = new Model\User;
			$user->login = $login;
			$user->password = $password;
			$user->save();
		}

		Art\User::set($user->to_json());

		$status = true;
	}

	return new Response(json_encode(array(
		'status' => $status,
		'id' => Art\User::get('id')
	)));
});

$app->post('/user/logout', function(Request $request) use($app) {
	Art\User::logout();

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/addpl', function(Request $request) use($app) {
	$userId = $app['user']::get('id');

	if ( $userId ) {
		$pl = new \Model\Pl;
		$pl->userid = $userId;
		$pl->name = $request->get('name', "New playlist");

		$pl->save();
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/addtrackToPl', function(Request $request) use($app) {
	$userId = $app['user']::get('id');

	$pl = \Model\Pl::find_by_userid_and_id($userId, $request->get('playlistId'));

	if ( $pl ) {
		$vkid = $request->get('vkId');

		$vkTrack = \Project\Cache::get("vk_track_{$vkid}", 60*60*24*7, function() use ( $app, $vkid ) {
	    return $app['openplayer']->audioGetById( $vkid );
	  });

		$plTrack = \Model\PlTrack::find_by_plid_and_vkid($pl->id, $vkid);

		if ( !$plTrack ) {
			$plTrack = new \Model\PlTrack;
			$plTrack->plid = $pl->id;
			$plTrack->artist = $vkTrack->artist;
			$plTrack->name = $vkTrack->title;
			$plTrack->vkid = $vkid;
			$plTrack->lyricsid = $vkTrack->lyrics_id;
			$plTrack->duration = $vkTrack->duration;
			$plTrack->save();
		}
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/pltrackremove', function(Request $request) use($app) {
	$userId = $app['user']::get('id');

	$pl = \Model\Pl::find_by_userid_and_id($userId, $request->get('playlistId'));

	if ( $pl ) {
		$vkid = $request->get('vkId');
		$plTrack = \Model\PlTrack::find_by_plid_and_vkid($pl->id, $vkid);

		if ( $plTrack ) {
			$plTrack->delete();
		}
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/renamepl', function(Request $request) use($app) {
	$userId = $app['user']::get('id');

	$pl = \Model\Pl::find_by_userid_and_id($userId, $request->get('id'));

	if ( $pl ) {
		$pl->name = $request->get('name', "New playlist");
		$pl->save();
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/expandpl', function(Request $request) use($app) {
	$userId = $app['user']::get('id');

	$pl = \Model\Pl::find_by_userid_and_id($userId, $request->get('id'));

	if ( $pl ) {
		$pl->status = $request->get('status', 0);
		$pl->save();
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

$app->post('/user/delpl', function(Request $request) use($app) {
	$userId = $app['user']::get('id');
	$pl = \Model\Pl::find_by_userid_and_id($userId, $request->get('id'));

	if ( $pl ) {
		$pl->delete();
	}

	return new Response(json_encode(array(
		'status' => true
	)));
});

function playlists() {
	$app = Reg::get('app');
	$pls = array();
	$pltrs = array();

	if ( $app['user']::get('id') ) {
		$pls = Model\Pl::find_all_by_userid( $app['user']::get('id'), array('order' => 'pos') );

		$plids = array();

		foreach ( $pls as $pl ) {
			$plids[] = $pl->id;
		}

		if ( $plids ) {
			$pltracks = Model\PlTrack::find_all_by_plid( $plids, array('order' => 'pos') );

			$pltrs = array();
			foreach ( $pltracks as $pltrack ) {
				$pltrs[$pltrack->plid][] = $pltrack;
			}
		}
	}

	return $app['view']->render(null, "part/playlist.phtml", array(
		'pls' => $pls,
		'pltrs' => $pltrs
	));
}

function lastfmchart() {
	$app = Reg::get('app');
	$lastfmdata = Art\LastFM::request($app['conf'], "chart.getTopTracks", array(
        "page" => 0,
        "limit" => 20
    ));

    $tracks = array();
    if ( isset($lastfmdata->tracks) ) {
    	$tracks = $lastfmdata->tracks->track;
    }

    $rtracks = array();
    foreach ( $tracks as $track ) {
    	$artist = $track->artist->name;
    	$name = $track->name;

    	$vtrack = $app['openplayer']->audioSearch( 
	        "{$artist} - {$name}", 0, 1
	    );

	    $vtrack = $vtrack['result'][0];

    	$rtracks[] = array(
    		'vkid' => "{$vtrack['owner_id']}_{$vtrack['aid']}",
    		'lyrics_id' => $vtrack['lyrics_id'],
    		'artist' => $artist,
    		'title' => $name,
    		'duration' => $vtrack['duration']
		);
    }

	return $app['view']->render(null, "part/lastfmchart.phtml", array(
		'tracks' => $rtracks,
	));
}

$app->get('/user/widget/pl', function(Request $request) use($app) {
	return playlists();
});