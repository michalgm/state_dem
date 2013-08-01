<?php
$apiKey = "key=3420aea61e2f4cb1a8a925a0c738eaf0";
$sunlightKey = "3d31e77632dee1932263856761f7494b";
$ftmUrl = "http://api.followthemoney.org/";
$ob_end_flush;
$db = "";
$dblogin = 'oilchange';
$dbhost = '192.168.2.2';
#$dbhost = 'localhost';
$dblogin = 'oilchange';
$dbpass = 'oilchange';
$dbname = 'state_dem';
$dbport = "3306";
#$dbsocket = "/tmp/mysql.sock";

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/www/NodeViz/library/');

$debug=1;

require_once('dbaccess.php');

$states = fetchCol("select state from states");

$min_cycle = 2006;
$max_cycle = 2012;

$remotecache = "";

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
