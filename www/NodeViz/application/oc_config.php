<?php
require_once("oc_utils.php");
require_once("dbaccess.php");
/*
if(file_exists('utils.php')) { include_once("utils.php"); }
if(file_exists('oc_utils.php')) { include_once("oc_utils.php"); }
if(file_exists('dbaccess.php')) { include_once("dbaccess.php"); }
if(file_exists('../../utils.php')) { include_once("../../utils.php"); }
if(file_exists('../../oc_utils.php')) { include_once("../../oc_utils.php"); }
if(file_exists('../../dbaccess.php')) { include_once("../../dbaccess.php"); }
 */

$electionyear = '08';
$dbhost = '192.168.2.2';
$dblogin = 'oilchange';
$dbpass = 'oilchange';
$dbname = 'turboprop';
$dbport = "3306";
$dbsocket = "";
$candidate_images = "can_images/";
$company_images = "com_images/";
$datapath = "cache/";
$db; //don't touch this - it's the db connection cache

//FEC cobol replacement table
$cobol = array("]"=>0, 'J'=>1, 'K'=>2, 'L'=>3, 'M'=>4, 'N'=>5, 'O'=>6, 'P'=>7, 'Q'=>8, 'R'=>9, 'j'=>1, 'k'=>2, 'l'=>3, 'm'=>4, 'n'=>5, 'o'=>6, 'p'=>7, 'q'=>8, 'r'=>9 );


$congresses = array( 
	"111" => '111th (2009-2010)',
	"110" => '110th (2007-2008)',
	"109" => '109th (2005-2006)',
	"108" => '108th (2003-2004)',
	"107" => '107th (2001-2002)',
	"106" => '106th (1999-2000)'
);

$current_congress = max(array_keys($congresses));

//Party color table
$partycolor = array(
	'REP'=>'#CC6666',
	'R'=>'#CC6666',
	'DEM'=>'#6699cc',
	'D'=>'#06699cc',
	'IND'=>'#cccc33',
	'I'=>'#cccc33',
	'GRE'=>'#66CC66',
	'G'=>'#66CC66',
	'LIB'=>'#66CCCC',
	'L'=>'#66CCCC',
	'DFL'=>'#6699CC',
	'COAL'=>'#6d8f9d',
	'OIL'=>'#958d63',
	'CARBON'=>'#666666'
);

//checkRequestHeaders();

function checkRequestHeaders() {
	global $_GET;
	global $_SERVER;

	if (!$_GET) { return; }
	if (strpos($_SERVER['PHP_SELF'], 'request.php') || strpos($_SERVER['PHP_SELF'], 'index.php')) { return; }

	$current_url =  'http://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	$page = $_SERVER['PHP_SELF'];

	$parts = explode('.', $_SERVER['SERVER_NAME']);
	$subdomain = "";
	if (count($parts) > 2) { 
		while (count($parts) >3) { array_shift($parts); }
		$subdomain = array_shift($parts);
	}
	list($domain, $tld) = $parts;
	#if ($domain == 'styrotopia') { $domain = 'oilchange.styrotopia'; }		
	if ($subdomain) { 
		if ($subdomain == 'prezoilmoney') { 
			$_GET['type'] = 'presidential';
			$subdomain = "";
		} else if ($subdomain == 'coalmoney') { 
			$_GET['sitecode'] = 'coal';
			$subdomain = "";
		} else if ($subdomain == 'oilmoney') { 
			$_GET['sitecode'] = 'oil';
			$subdomain = "";
		}
	}

	foreach(array('contrib', 'candidate', 'company') as $filter) {
		$oldfilter = 'min'.ucwords($filter).'Amount';
		if(isset($_GET[$oldfilter])) { 
			$_GET[$filter.'FilterIndex'] = $_GET[$oldfilter];
			unset($_GET[$oldfilter]);
		}
	}
	if (isset($_GET['v']) && $_GET['v'] == 'graphs') { 
		unset($_GET['v']);
	}
	if ($page == '/view.php' && isset($_GET['type'])) { 
		$type = $_GET['type'];
		if ($type != 'congress' && $type != 'presidential' && $type != 'search') {
			if ($type == 'oildollars') { 
				$_GET['type'] = 'search';
			} else { 
				unset($_GET['type']);
				$page = '/404.php';
			}
		}
	}
	if ($subdomain != '') { $subdomain .= '.'; }
	$query = http_build_query($_GET);
	$new_url = "http://$subdomain$domain.$tld$page?$query";

	if ($current_url != $new_url) { 
		if (! $debug) { 
			header("Location: $new_url", TRUE, 301); exit;
		} else { 
			print "Trying to do a redirect and you have debug enabled<br/> Old URL: $current_url<br>New URL: $new_url"; exit;
		}
	}
}

?>
