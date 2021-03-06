<?php

//
// THIS IS NOT YET COMPLETED BUT IT IS A REWRITE OF PMSF AND COMPATIBLE WITH THE LATEST ROCKETMAP FRONTEND
// 
// CHANGES: 
// * IMPROVED SECURITY
// * RETURNING FULL POKEMON LIST ONLY ONCE EVERY 45 SECONDS
// * CHANGED QUERY AND HANDLING ( NO CPU SPIKES! )

header('content-type: application/json');
header("Access-Control-Allow-Origin: YOURHOST.TLD");

$referer_raw = $_SERVER['HTTP_REFERER'];
$referer_url = parse_url($referer_raw);
$referer_host = $referer_url['host'];
$allowed_referers = array('yourdomain.nl', 'sub.yourdomain.tld', '127.0.0.1'); //127.0.0.1 for ex: local testing

$d = array();

if(!in_array($referer_host, $allowed_referers)){
    $d['status'] = "referer-not-allowed: $referer_host";
    http_response_code(400);
    die();
 } 
else{
    $d['status'] = "OK";
}

include ('utils/utils.php');
include ('../config/config.php');

session_start();
if (!isTrue($_SESSION['time_issued']) || !isTrue($_SESSION['time_left']) || $_SESSION['time_left'] <= 0) {
  unset($_SESSION['token']);
  unset($_SESSION['time_issued']);
  unset($_SESSION['time_left']);
  $session_token = $_SESSION['token'] = base64_encode(openssl_random_pseudo_bytes(32));
  $session_time_issued = $_SESSION['time_issued'] = time();
  $session_time_left = $_SESSION['time_left'] = $sessionTTL;
}
else {
  $session_token = $_SESSION['token'];
  $session_time_issued = $_SESSION['time_issued'];
  $session_time_left = $_SESSION['time_left'] = ($sessionTTL - (time() - $session_time_issued));
}
session_write_close();


function isTrue($var)
{
  return (isset($var) && !empty($var) && !is_null($var));
}

$now = new DateTime();
$d = array();
$d['timestamp'] = $now->getTimestamp();
$swLat = isTrue($_POST['swLat']) ? $_POST['swLat'] : 0;
$neLng = isTrue($_POST['neLng']) ? $_POST['neLng'] : 0;
$swLng = isTrue($_POST['swLng']) ? $_POST['swLng'] : 0;
$neLat = isTrue($_POST['neLat']) ? $_POST['neLat'] : 0;
$oSwLat = isTrue($_POST['oSwLat']) ? $_POST['oSwLat'] : 0;
$oSwLng = isTrue($_POST['oSwLng']) ? $_POST['oSwLng'] : 0;
$oNeLat = isTrue($_POST['oNeLat']) ? $_POST['oNeLat'] : 0;
$oNeLng = isTrue($_POST['oNeLng']) ? $_POST['oNeLng'] : 0;
$luredonly = isTrue($_POST['luredonly']) ? $_POST['luredonly'] : false;
$lastpokemon = isTrue($_POST['lastpokemon']) ? $_POST['lastpokemon'] : false;
$lastgyms = isTrue($_POST['lastgyms']) ? $_POST['lastgyms'] : false;
$lastpokestops = isTrue($_POST['lastpokestops']) ? $_POST['lastpokestops'] : false;
$lastlocs = isTrue($_POST['lastslocs']) ? $_POST['lastslocs'] : false;
$lastspawns = isTrue($_POST['lastspawns']) ? $_POST['lastspawns'] : false;
$d['lastpokestops'] = isTrue($_POST['pokestops']) ? $_POST['pokestops'] : false;
$d['lastgyms'] = isTrue($_POST['gyms']) ? $_POST['gyms'] : false;
$d['lastslocs'] = isTrue($_POST['scanned']) ? $_POST['scanned'] : false;
$d['lastspawns'] = isTrue($_POST['spawnpoints']) ? $_POST['spawnpoints'] : false;
$d['lastpokemon'] = isTrue($_POST['pokemon']) ? $_POST['pokemon'] : false;
$timestamp = isTrue($_POST['timestamp']) ? $_POST['timestamp'] : 0;
$useragent = $_SERVER['HTTP_USER_AGENT'];
$eids = isset($_POST['eids']) && !empty($_POST['eids']) ? $_POST['eids'] : intval(0);
$reids = isset($_POST['eids']) && !empty($_POST['reids']) ? $_POST['reids'] : intval(0);

if (empty($swLat) || empty($swLng) || empty($neLat) || empty($neLng) || preg_match('/curl|libcurl/', $useragent)) {
  http_response_code(400);
  die();
}

if ($maxLatLng > 0 && ((($neLat - $swLat) > $maxLatLng) || (($neLng - $swLng) > $maxLatLng))) {
  http_response_code(400);
  die();
}

$newarea = false;

if (($oSwLng < $swLng) && ($oSwLat < $swLat) && ($oNeLat > $neLat) && ($oNeLng > $neLng)) {
  $newarea = false;
}
elseif (($oSwLat != $swLat) && ($oSwLng != $swLng) && ($oNeLat != $neLat) && ($oNeLng != $neLng)) {
  $newarea = true;
}
else {
  $newarea = false;
}

$d['time_until_full_scan'] = $session_time_left;
$d['oSwLat'] = $swLat;
$d['oSwLng'] = $swLng;
$d['oNeLat'] = $neLat;
$d['oNeLng'] = $neLng;


global $noPokemon;
if (!$noPokemon) {
  if ($d['lastpokemon'] == 'true') {
    if ($lastpokemon != 'true') {
      $d['pokemons'] = get_active($swLat, $swLng, $neLat, $neLng, $eids);
    }
    else {
      if ($newarea) {
        $d['pokemons'] = get_active($swLat, $swLng, $neLat, $neLng, $timestamp, $oSwLat, $oSwLng, $oNeLat, $oNeLng, $eids);
      }
      else {
        if ($session_time_left >= 1) {
          $d['pokemons'] = [];
        }
        else {
          $d['pokemons'] = get_active($swLat, $swLng, $neLat, $neLng, $timestamp, $eids);
        }
      }
    }

    if (isTrue($_POST['reids'])) {
      $reids = explode(',', $_POST['reids']);
      $d['pokemons'] = $d['pokemons'] + (get_active_by_id($reids, $swLat, $swLng, $neLat, $neLng));
      $d['reids'] = !empty($_POST['reids']) ? $reids : null;
    }
  }
}


global $noPokestops;
if (!$noPokestops) {
  if ($d['lastpokestops'] == 'true') {
    if ($lastpokestops != 'true') {
      $d['pokestops'] = get_stops($swLat, $swLng, $neLat, $neLng, 0, 0, 0, 0, 0, $luredonly);
    }
    else {
      if ($newarea) {
        $d['pokestops'] = get_stops($swLat, $swLng, $neLat, $neLng, 0, $oSwLat, $oSwLng, $oNeLat, $oNeLng, $luredonly);
      }
      else {
        $d['pokestops'] = get_stops($swLat, $swLng, $neLat, $neLng, $timestamp, 0, 0, 0, 0, $luredonly);
      }
    }
  }
}


global $noGyms
if (!$noGyms) {
  if ($d['lastgyms'] == 'true') {
    if ($lastgyms != 'true') {
      $d['gyms'] = get_gyms($swLat, $swLng, $neLat, $neLng);
    }
    else {
      if ($newarea) {
        $d['gyms'] = get_gyms($swLat, $swLng, $neLat, $neLng, 0, $oSwLat, $oSwLng, $oNeLat, $oNeLng);
      }
      else {
        $d['gyms'] = get_gyms($swLat, $swLng, $neLat, $neLng, $timestamp);
      }
    }
  }
}


$jaysson = json_encode($d, false);
echo $jaysson;

function get_active($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0, $eids = 0)
{
  global $db;
  $datas = array();
  if ($swLat == 0) {
    $datas = $db->query('SELECT * FROM sightings WHERE pokemon_id NOT IN ( ' . $eids . ') AND expire_timestamp > :time', ['time' => time() ])->fetchAll();
  }
  elseif ($tstamp > 0) {
    $datas = $db->query('SELECT * 
        FROM   sightings 
        WHERE  expire_timestamp > :time 
        AND    pokemon_id NOT IN ( ' . $eids . ')
        AND    lat > :swLat 
        AND    lon > :swLng 
        AND    lat < :neLat 
        AND    lon < :neLng', [':time' => time() , ':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }
  elseif ($oSwLat != 0) {
    $datas = $db->query('SELECT * 
FROM   sightings 
WHERE  expire_timestamp > :time 
       AND pokemon_id NOT in ( ' . $eids . ')
       AND lat > :swLat
       AND lon > :swLng 
       AND lat < :neLat 
       AND lon < :neLng 
       AND NOT( lat > :oSwLat 
                AND lon > :oSwLng 
                AND lat < :oNeLat 
                AND lon < :oNeLng ) ', [':time' => time() , ':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng, ':oSwLat' => $oSwLat, ':oSwLng' => $oSwLng, ':oNeLat' => $oNeLat, ':oNeLng' => $oNeLng])->fetchAll();
  }
  else {
    $datas = $db->query('SELECT * 
      FROM   sightings 
      WHERE  expire_timestamp > :time 
      AND    pokemon_id NOT IN ( ' . $eids . ')
      AND    lat > :swLat 
      AND    lon > :swLng 
      AND    lat < :neLat 
      AND    lon < :neLng', [':time' => time() , ':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }

  $pokemons = array();
  $json_poke = '../Rocketmap/static/data/pokemon.json';
  $json_contents = file_get_contents($json_poke);
  $data = json_decode($json_contents, TRUE);
  $i = 0;

  /* fetch associative array */
  foreach($datas as $row) {
    $p = array();
    $disappear = isTrue($row['expire_timestamp']) ? $row['expire_timestamp'] * 1000 : null;
    $lat = isTrue($row['lat']) ? floatval($row['lat']) : null;
    $lon = isTrue($row['lon']) ? floatval($row['lon']) : null;
    $pokeid = isTrue($row['pokemon_id']) ? intval($row['pokemon_id']) : null;
    $atk = isTrue($row['atk_iv']) ? intval($row['atk_iv']) : 0;
    $def = isTrue($row['def_iv']) ? intval($row['def_iv']) : 0;
    $sta = isTrue($row['sta_iv']) ? intval($row['sta_iv']) : 0;
    $mv1 = isTrue($row['move_1']) ? intval($row['move_1']) : 0;
    $mv2 = isTrue($row['move_2']) ? intval($row['move_2']) : 0;
    $weight = isTrue($row['weight']) ? floatval($row['weight']) : 0;
    $height = isTrue($row['height']) ? floatval($row['height']) : 0;
    $gender = isTrue($row['gender']) ? floatval($row['gender']) : 4;
    $form = isTrue($row['form']) ? intval($row['form']) : 0;
    $cp = isTrue($row['cp']) ? intval($row['cp']) : 0;
    $cpm = isTrue($row['cp_multiplier']) ? floatval($row['cp_multiplier']) : 0;
    $level = isTrue($row['level']) ? intval($row['level']) : 0;
    $p['disappear_time'] = $disappear;
    $p['encounter_id'] = $row['encounter_id'];
    
    global $noHighLevelData;
    if (!$noHighLevelData) {
      $p['individual_attack'] = $atk;
      $p['individual_defense'] = $def;
      $p['individual_stamina'] = $sta;
      $p['move_1'] = $mv1;
      $p['move_2'] = $mv2;
      $p['weight'] = $weight;
      $p['height'] = $height;
      $p['cp'] = $cp;
      $p['cp_multiplier'] = $cpm;
      $p['level'] = $level;
    }

    $p['latitude'] = $lat;
    $p['longitude'] = $lon;
    $p['gender'] = $gender;
    $p['form'] = $form;
    $p['pokemon_id'] = $pokeid;
    $p['pokemon_name'] = i8ln($data[$pokeid]['name']);
    $p['pokemon_rarity'] = i8ln($data[$pokeid]['rarity']);
    $types = $data[$pokeid]['types'];
    foreach($types as $k => $v) {
      $types[$k]['type'] = i8ln($v['type']);
    }

    $p['pokemon_types'] = $types;
    $p['spawnpoint_id'] = $row['spawn_id'];
    $pokemons[] = ($p);
    unset($datas[$i]);
    $i++;
  }

  return $pokemons;
}

function get_active_by_id($ids, $swLat, $swLng, $neLat, $neLng, $reids)
{
  global $db;
  $datas = array();
  $pkmn_in = '';
  if ($swLat == 0) {
    $datas = $db->query('SELECT * 
FROM   sightings 
WHERE  `expire_timestamp` > :time
       AND pokemon_id IN ( $pkmn_in ) ', array_merge($pkmn_ids, [':time' => time() ]))->fetchAll();
  }
  else {
    $datas = $db->query('SELECT * 
FROM   sightings 
WHERE  expire_timestamp > :timeStamp
AND    pokemon_id IN ( $reids ) 
AND    lat > :swLat 
AND    lon > :swLng
AND    lat < :neLat
AND    lon < :neLng', array_merge($pkmn_ids, [':timeStamp' => time() , ':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng]))->fetchAll();
  }

  $pokemons = array();
  $json_poke = '../static/data/pokemon.json';
  $json_contents = file_get_contents($json_poke);
  $data = json_decode($json_contents, TRUE);
  $i = 0;

  foreach($datas as $row) {
    $p = array();
    $disappear = $row['expire_timestamp'] * 1000;
    $lat = floatval($row['lat']);
    $lon = floatval($row['lon']);
    $pokeid = intval($row['pokemon_id']);
    $atk = isTrue($row['atk_iv']) ? intval($row['atk_iv']) : null;
    $def = isTrue($row['def_iv']) ? intval($row['def_iv']) : null;
    $sta = isTrue($row['sta_iv']) ? intval($row['sta_iv']) : null;
    $mv1 = isTrue($row['move_1']) ? intval($row['move_1']) : null;
    $mv2 = isTrue($row['move_2']) ? intval($row['move_2']) : null;
    $weight = isTrue($row['weight']) ? floatval($row['weight']) : null;
    $height = isTrue($row['height']) ? floatval($row['height']) : null;
    $gender = isTrue($row['gender']) ? intval($row['gender']) : 4;
    $form = isTrue($row['form']) ? intval($row['form']) : null;
    $cp = isTrue($row['cp']) ? intval($row['cp']) : null;
    $cpm = isTrue($row['cp_multiplier']) ? floatval($row['cp_multiplier']) : null;
    $level = isTrue($row['level']) ? intval($row['level']) : null;
    
    $p['encounter_id'] = $row['encounter_id'];

    $p['disappear_time'] = $disappear;
    $p['individual_attack'] = $atk;
    $p['individual_defense'] = $def;
    $p['individual_stamina'] = $sta;
    $p['move_1'] = $mv1;
    $p['move_2'] = $mv2;
    $p['weight'] = $weight;
    $p['height'] = $height;
    $p['cp'] = $cp;
    $p['cp_multiplier'] = $cpm;
    $p['level'] = $level;
    $p['latitude'] = $lat;
    $p['longitude'] = $lon;
    $p['gender'] = $gender;
    $p['form'] = $form;
    $p['pokemon_id'] = $pokeid;
    $p['pokemon_name'] = i8ln($data[$pokeid]['name']);
    $p['pokemon_rarity'] = i8ln($data[$pokeid]['rarity']);
    $p['pokemon_types'] = $data[$pokeid]['types'];
    $p['spawnpoint_id'] = $row['spawn_id'];
    $pokemons[] = $p;

    unset($datas[$i]);

    $i++;
  }

  return $pokemons;
}

function get_stops($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0, $lured = false)
{
  global $db;
  $datas = array();
  if ($swLat == 0) {
    $datas = $db->query('SELECT external_id, lat, lon FROM pokestops')->fetchAll();
  }
  elseif ($tstamp > 0) {
    $datas = $db->query('SELECT external_id, 
       lat, 
       lon 
FROM   pokestops 
WHERE  lat > :swLat 
AND    lon > :swLng 
AND    lat < :neLat 
AND    lon < :neLng', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }
  elseif ($oSwLat != 0) {
    $datas = $db->query('SELECT external_id, 
       lat, 
       lon 
FROM   pokestops 
WHERE  lat > :swLat
       AND lon > :swLng 
       AND lat < :neLat 
       AND lon < :neLng
       AND NOT( lat > :oSwLat 
                AND lon > :oSwLng 
                AND lat < :oNeLat 
                AND lon < :oNeLng ) ', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng, ':oSwLat' => $oSwLat, ':oSwLng' => $oSwLng, ':oNeLat' => $oNeLat, ':oNeLng' => $oNeLng])->fetchAll();
  }
  else {
    $datas = $db->query('SELECT external_id, 
       lat, 
       lon 
FROM   pokestops 
WHERE  lat > :swLat 
AND    lon > :swLng 
AND    lat < :neLat 
AND    lon < :neLng', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }

  $i = 0;
  $pokestops = array();
  foreach($datas as $row) {
    $p = array();
    $lat = floatval($row['lat']);
    $lon = floatval($row['lon']);
    $p['active_fort_modifier'] = isTrue($row['active_fort_modifier']) && !empty($row['active_fort_modifier']) ? $row['active_fort_modifier'] : null;
    $p['enabled'] = isTrue($row['enabled']) && !empty($row['enabled']) ? boolval($row['enabled']) : true;
    $p['last_modified'] = isTrue($row['last_modified']) && !empty($row['last_modified']) ? $row['last_modified'] * 1000 : 0;
    $p['latitude'] = $lat;
    $p['longitude'] = $lon;
    $p['lure_expiration'] = isTrue($row['lure_expiration']) && !empty($row['lure_expiration']) ? $row['lure_expiration'] * 1000 : null;
    $p['pokestop_id'] = $row['external_id'];
    $pokestops[] = $p;
    unset($datas[$i]);
    $i++;
  }

  return $pokestops;
}

function get_gyms($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
{
  global $db;
  $datas = array();
  if ($swLat == 0) {
    $datas = $db->query('SELECT t3.external_id, 
       t3.lat, 
       t3.lon, 
       t1.last_modified, 
       t1.team, 
       t1.slots_available, 
       t1.guard_pokemon_id,
       t4.raid_seed,
       t4.raid_spawn,
       t4.raid_level,
       t4.raid_start,
       t4.raid_end,
       t4.pokemon_id,
       t4.cp,
       t4.move_1,
       t4.move_2
FROM   (SELECT fort_id, 
               Max(last_modified) AS MaxLastModified 
        FROM   fort_sightings 
        GROUP  BY fort_id) t2 
      LEFT JOIN fort_sightings t1 
              ON t2.fort_id = t1.fort_id 
                 AND t2.maxlastmodified = t1.last_modified 
      LEFT JOIN forts t3 
              ON t1.fort_id = t3.id
      LEFT JOIN raid_info t4
              ON t2.fort_id = t4.fort_id')->fetchAll();
  }
  elseif ($tstamp > 0) {
    $datas = $db->query('SELECT t3.external_id, 
       t3.lat, 
       t3.lon, 
       t1.last_modified, 
       t1.team, 
       t1.slots_available, 
       t1.guard_pokemon_id,
       t4.raid_seed, 
       t4.raid_spawn,
       t4.raid_level,
       t4.raid_start,
       t4.raid_end,
       t4.pokemon_id,
       t4.cp,
       t4.move_1,
       t4.move_2
FROM   (SELECT fort_id, 
               Max(last_modified) AS MaxLastModified 
        FROM   fort_sightings 
        GROUP  BY fort_id) t2 
      LEFT JOIN fort_sightings t1 
              ON t2.fort_id = t1.fort_id 
                 AND t2.maxlastmodified = t1.last_modified 
      LEFT JOIN forts t3 
              ON t1.fort_id = t3.id 
      LEFT JOIN raid_info t4
              ON t2.fort_id = t4.fort_id
WHERE  t3.lat > :swLat 
       AND t3.lon > :swLng 
       AND t3.lat < :neLat 
       AND t3.lon < :neLng', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }
  elseif ($oSwLat != 0) {
    $datas = $db->query('SELECT t3.external_id, 
       t3.lat, 
       t3.lon, 
       t1.last_modified, 
       t1.team, 
       t1.slots_available, 
       t1.guard_pokemon_id,
       t4.raid_seed,
       t4.raid_spawn,
       t4.raid_level,
       t4.raid_start,
       t4.raid_end,
       t4.pokemon_id,
       t4.cp,
       t4.move_1,
       t4.move_2
FROM   (SELECT fort_id, 
               Max(last_modified) AS MaxLastModified 
        FROM   fort_sightings 
        GROUP BY fort_id) t2 
      LEFT JOIN fort_sightings t1 
              ON t2.fort_id = t1.fort_id 
                 AND t2.maxlastmodified = t1.last_modified 
      LEFT JOIN forts t3 
              ON t1.fort_id = t3.id 
            LEFT JOIN raid_info t4
              ON t2.fort_id = t4.fort_id
WHERE  t3.lat > :swLat 
       AND t3.lon > :swLng
       AND t3.lat < :neLat
       AND t3.lon < :neLng
       AND NOT( t3.lat > :oSwLat
                AND t3.lon > :oSwLng
                AND t3.lat < :oNeLat
                AND t3.lon < :oNeLng)', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng, ':oSwLat' => $oSwLat, ':oSwLng' => $oSwLng, ':oNeLat' => $oNeLat, ':oNeLng' => $oNeLng])->fetchAll();
  }
  else {
    $datas = $db->query('SELECT t3.external_id, 
       t3.lat, 
       t3.lon, 
       t1.last_modified, 
       t1.team, 
       t1.slots_available, 
       t1.guard_pokemon_id,
       t4.raid_seed,
       t4.raid_spawn,
       t4.raid_level,
       t4.raid_start,
       t4.raid_end,
       t4.pokemon_id,
       t4.cp,
       t4.move_1,
       t4.move_2
FROM   (SELECT fort_id, 
               Max(last_modified) AS maxlastmodified 
        FROM   fort_sightings 
        GROUP BY fort_id) t2 
      LEFT JOIN fort_sightings t1 
            ON        t2.fort_id = t1.fort_id 
                AND       t2.maxlastmodified = t1.last_modified 
      LEFT JOIN forts t3 
            ON        t1.fort_id = t3.id 
      LEFT JOIN raid_info t4
            ON t2.fort_id = t4.fort_id
WHERE t3.lat > :swLat
        AND t3.lon > :swLng 
        AND t3.lat < :neLat 
        AND t3.lon < :neLng', [':swLat' => $swLat, ':swLng' => $swLng, ':neLat' => $neLat, ':neLng' => $neLng])->fetchAll();
  }

  $i = 0;
  $gyms = array();
  $gym_ids = array();
  $json_poke = '../static/data/pokemon.json';
  $json_contents = file_get_contents($json_poke);
  $data = json_decode($json_contents, TRUE);
  foreach($datas as $row) {
    
    $lat = floatval($row['lat']);
    $lon = floatval($row['lon']);
    $gpid = intval($row['guard_pokemon_id']);
    $lm = $row['last_modified'] * 1000;
    $ls = isTrue($row['last_scanned']) && !empty($row['last_scanned']) ? $row['last_scanned'] * 1000 : null;
    $ti = isTrue($row['team']) && !empty($row['team']) ? intval($row['team']) : null;
    $tc = isTrue($row['total_cp']) && !empty($row['total_cp']) ? intval($row['total_cp']) : null;
    $sa = isTrue($row['slots_available']) && !empty($row['slots_available']) ? intval($row['slots_available']) : 0;

    $p = array();
    $raid = array();

    $p['enabled'] = isTrue($row['enabled']) && !empty($row['enabled']) ? boolval($row['enabled']) : true;
    $p['guard_pokemon_id'] = $gpid;
    $p['gym_id'] = $row['external_id'];
    $p['slots_available'] = $sa;
    $p['last_modified'] = $lm;
    $p['last_scanned'] = $lm;
    $p['latitude'] = $lat;
    $p['longitude'] = $lon;
    $p['name'] = isTrue($row['name']) && !empty($row['name']) ? $row['name'] : null;
    $p['pokemon'] = [];
    $p['team_id'] = isTrue($row['team']) && !empty($row['team']) ? $row['team'] : null;
    $p['total_cp'] = null;

    $raid['seed'] = isTrue($row['raid_seed']) && !empty($row['raid_seed']) ? intval($row['raid_seed']) : null;
    $raid['level'] = isTrue($row['raid_level']) && !empty($row['raid_level']) ? intval($row['raid_level']) : 0;
    $raid['spawn'] = isTrue($row['raid_spawn']) && !empty($row['raid_spawn']) ? intval($row['raid_spawn']) * 1000 : 0;
    $raid['start'] = isTrue($row['raid_start']) && !empty($row['raid_start']) ? intval($row['raid_start']) * 1000 : 0;
    $raid['end'] = isTrue($row['raid_end']) && !empty($row['raid_end']) ? intval($row['raid_end']) * 1000 : 0;
    $raid['pokemon_id'] = isTrue($row['pokemon_id']) && !empty($row['pokemon_id']) ? intval($row['pokemon_id']) : null;
    $raid['cp'] = isTrue($row['cp']) && !empty($row['cp']) ? intval($row['cp']) : null;
    $raid['move_1'] = isTrue($row['move_1']) && !empty($row['move_1']) ? intval($row['move_1']) : null;
    $raid['move_2'] = isTrue($row['move_2']) && !empty($row['move_2']) ? intval($row['move_2']) : null;
    $raid['team_id'] = isTrue($row['team']) && !empty($row['team']) ? intval($row['team']) : null;
    $raid['total_cp'] = 0;
    $raid['gym_id'] = isTrue($row['external_id']) && !empty($row['external_id']) ? $row['external_id'] : null;
    $raid['last_scanned'] = $lm;
    $raid['slots_available'] = $sa;
    $raid['pokemon_name'] = isTrue($data[$raid['pokemon_id']]['name']) ? i8ln($data[$raid['pokemon_id']]['name']) : null;
    $raid['pokemon_rarity'] = isTrue($data[$raid['pokemon_id']]['rarity']) ? i8ln($data[$raid['pokemon_id']]['rarity']) : null;
    $raid['pokemon_types'] = isTrue($data[$raid['pokemon_id']]['types']) ? i8ln($data[$raid['pokemon_id']]['types']) : null;
    
    $p['raid'] = $raid;
    
    $gyms[$row['external_id']] = $p;
    unset($datas[$i]);
    $i++;
  }

  return $gyms;
}

function get_recent($swLat, $swLng, $neLat, $neLng, $tstamp = 0, $oSwLat = 0, $oSwLng = 0, $oNeLat = 0, $oNeLng = 0)
{
  global $db;
  $datas = array();
  $recent = array();
  $i = 0;
  foreach($datas as $row) {
    $p = array();
    $p['latitude'] = floatval($row['latitude']);
    $p['longitude'] = floatval($row['longitude']);
    $lm = $row['last_modified'] * 1000;
    $p['last_modified'] = $lm;
    $recent[] = $p;
    unset($datas[$i]);
    $i++;
  }
  return $recent;
}
