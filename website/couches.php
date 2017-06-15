<?php

$layers = array(
  array('title' => "Wild/crossing animals or deer",
        'request' => array(
          '"hazard"="wild_animals"', 
          '"hazard"="animal_crossing"', 
          '"hazard"="animals_crossing"', 
          '"hazard"="deer"'
        ),
        'iconUrl' => 'images/A15b.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Moose",
        'request' => array(
          '"hazard"="moose"' 
        ),
        'iconUrl' => 'images/moose.png',
        'iconSize' => array(30, 30)
  ),
  array('title' => "Sharks",
        'request' => array(
          '"hazard"="shark"' 
        ),
        'iconUrl' => 'images/shark.png',
        'iconSize' => array(30, 30)
  ),
  array('title' => "Crocodiles",
        'request' => array(
          '"hazard"="crocodile"' 
        ),
        'iconUrl' => 'images/crocodile.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Danger(ous) children",
        'request' => array(
          '"hazard"="children"' 
        ),
        'iconUrl' => 'images/A13a.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Pedestrian",
        'request' => array(
          '"hazard"="pedestrian"' 
        ),
        'iconUrl' => 'images/A13b.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Horseriders",
        'request' => array(
          '"hazard"="horses"' 
        ),
        'iconUrl' => 'images/A15c.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Cyclists",
        'request' => array(
          '"hazard"="cyclists"' 
        ),
        'iconUrl' => 'images/A21.png',
        'iconSize' => array(30, 27)
  ),
  array('title' => "Robots",
        'request' => array(
          '"hazard"="robot"' 
        ),
        'iconUrl' => 'images/robot.png',
        'iconSize' => array(30, 27)
  )
);

function Refresh($layers) {
  $pdo = new PDO('sqlite:layers.db');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA journal_mode = OFF');
  $pdo->exec("CREATE TABLE IF NOT EXISTS 'layers' (
                layer TEXT,
                json TEXT        
              )");
  $pdo->exec("CREATE INDEX IF NOT EXISTS i_layer ON layers(layer)");
  $pdo->exec("DELETE FROM 'layers'");
  
  $stm = $pdo->prepare("INSERT INTO 'layers' VALUES (:layer, :json)");
  foreach($layers as $layer) {
    print("<h1>{$layer['title']}</h1>\n");
    
    $url = '[out:json];(';
    foreach($layer['request'] as $req) {
      $url .= "node[{$req}];";
      $url .= "way[{$req}];";
    }
    $url .= ');out meta qt;>;out skel qt;';
  
    $rep = file_get_contents('http://overpass-api.de/api/interpreter?data='.urlencode($url));
    $json = json_decode($rep);
    
    foreach($json->elements as $elem) {
      if (!$stm->execute(array(
        ':layer' => $layer['title'],
        ':json' =>  json_encode($elem)
      ))) {
        echo "Erreur à l'insertion\n";
        print_r($stm->errorInfo());
        exit;
      }
    }
  
  }
}

function getLayer($layer) {
  $pdo = new PDO('sqlite:layers.db');
  
  $elements = array();

  $stm = $pdo->prepare("SELECT json FROM layers WHERE layer = :layer");
  $stm->execute(array(':layer' => $layer));

  $node = array();
  $way = array();
  while ($row = $stm->fetch()) {
    $obj = json_decode($row['json']);
    switch ($obj->type) {
      case 'node' :
        $node[$obj->id] = $obj;
        
        if (isset($obj->tags)) {
          $elements[] = array(
            'type' => "Feature",
            'properties' => $obj,
            'geometry' => array(
              'type' => "Point",
              'coordinates' => array($obj->lon, $obj->lat)
            )
          );
        }
      
        break;
      case 'way' :
        $way[$obj->id] = $obj;
        break;
      default : 
        die("Type d'objet {$obj->type} inattendu.");
    }
  }

  foreach($way as $w) {
    $c = array();
    foreach($w->nodes as $n) {
      $c[] = array($node[$n]->lon, $node[$n]->lat);
    }    
    $elements[] = array(
      'type' => "Feature",
      'properties' => $w,
      'geometry' => array(
        'type' => "LineString",
        'coordinates' => $c
      )
    );
  }
  
  return json_encode(array(
    'type' => "FeatureCollection",
    'features' => $elements
  ));
}

ignore_user_abort();

$fdate = @filectime('layers.db'); 
$etag = md5($_GET['layer'].$fdate);
header("Etag: $etag");
if (trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
  header("HTTP/1.1 304 Not Modified");
} else {

  if (isset($_GET['layer'])) {
    header('Content-type: application/vnd.geo+json; charset=utf-8');
    $content = getLayer($_GET['layer']);
    $contentLength = strlen($content);
  } else {
    $rep = array();
    foreach($layers as $l) {
      $rep[] = array(
        'title'    => $l['title'], 
        'iconUrl'  => $l['iconUrl'],
        'iconSize' => $l['iconSize'] 
      );
    }
    $content = json_encode($rep);
    $contentLength = strlen($content);
  }  
  header('Content-type: application/json; charset=utf-8');
  header('Connection: close');
  header("Content-Length: $contentLength");
  print($content);
  flush();

  $fdate = @filectime('layers.db'); 
  $date = new DateTime();
  if ( ($fdate === FALSE ? 0 : $fdate + 12 * 3600) < $date->getTimestamp() ) {
    Refresh($layers);
  }
}

?>