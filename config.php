<?php
$apiKey = "key=3420aea61e2f4cb1a8a925a0c738eaf0";
$sunlightKey = "3d31e77632dee1932263856761f7494b";
$ftmUrl = "http://api.followthemoney.org/";
$ob_end_flush;
$db = "";
$dblogin = 'oilchange';
$dbhost = '192.168.2.2';
$dblogin = 'oilchange';
$dbpass = 'oilchange';
$dbname = 'state_dem';
$dbport = "3306";
$dbsocket = "";

require_once('dbaccess.php');

$states = array(
	'ND'=>'North Dakota',
	'OH'=>'Ohio',
	'CA'=>'California',
	'PA'=>'Pennsylvania',
	'CO'=>'Colorado',
	'AK'=>'Alaska',
);

$min_cycle = 2006;

function xml2array($xml) {
        $object = new SimpleXMLElement($xml);
        $array = array();
        $meta = array();
        foreach($object->attributes() as $prop=>$value) {
                $meta[$prop] = "".$value;
        }
        foreach($object as $item) {
                $entry = array();
                foreach($item->attributes() as $prop=>$value) {
                        $entry[$prop] = "".$value;
                }
                $array[] = $entry;
        }
        return array($meta, $array);
}


?>
