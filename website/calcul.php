<?php

function lon2x($lon) { return deg2rad($lon); }
function lat2y($lat) { return log(tan(M_PI_4 + deg2rad($lat) / 2.0)); }
// function lon2x($lon) { return deg2rad($lon) * 6378137.0; }
// function lat2y($lat) { return log(tan(M_PI_4 + deg2rad($lat) / 2.0)) * 6378137.0; }
function x2lon($x) { return rad2deg($x / 6378137.0); }
function y2lat($y) { return rad2deg(2.0 * atan(exp($y / 6378137.0)) - M_PI_2); }

$url = 'http://overpass-api.de/api/interpreter?data=';
$req  = '[out:json];(';
$req .= 'node["hazard"="wild_animals"];';
$req .= 'way["hazard"="wild_animals"];';
$req .= 'node["hazard"="animal_crossing"];';
$req .= 'way["hazard"="animal_crossing"];';
$req .= 'node["hazard"="animals_crossing"];';
$req .= 'way["hazard"="animals_crossing"];';
$req .= 'node["hazard"="deer"];';
$req .= 'way["hazard"="deer"];';
$req .= ');out meta qt;>;out skel qt;';

$f = file_get_contents($url . urlencode($req));

$elements = json_decode($f)->elements; 

foreach($elements as $e) {
    switch ($e->type) {
        case 'node' :
            $node[$e->id] = new stdClass(); 
            $node[$e->id]->lat = $e->lat;
            $node[$e->id]->lon = $e->lon;
            $node[$e->id]->tags = $e->tags;
            break;
        case 'way' :
            $way[$e->id] = new stdClass(); 
            $way[$e->id]->nodes = $e->nodes;
            $way[$e->id]->tags = $e->tags;
            break;
        default:
            var_dump($e);
    }
}

$largeur = 8192;
$img = imagecreate($largeur, $largeur);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
$rouge = imagecolorallocatealpha($img, 255, 0, 0, 0);

imagefill($img, 0, 0, $transparent); 

foreach($node as $n) {
    $x = lon2x($n->lon);
    $y = lat2y($n->lat);
    
/*
echo "<pre>";
var_dump($x);
var_dump($y);
echo "</pre>";
*/
    
//var_dump(lat2y(-89.5));    
    
//    imagesetpixel($img, (-lon2x(-180) + $x) * $largeur / 6, (-lat2y(-89.5) - $y) * $largeur / 6, $rouge); 
    imagesetpixel($img, ($x - lon2x(-180)) / (lon2x(180) - lon2x(-180)) * $largeur, (-lat2y(-89.5) - $y * 1.75) / (lat2y(89.5) - lat2y(-89.5)) * $largeur, $rouge); 
}


header("Content-Type: image/png");
imagepng($img);
imagedestroy($img);

?>