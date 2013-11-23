<?php
$nodeViz_config= array(
	'nodeViz_path' => getcwd(),
	'web_path' => '../',
	'application_path' => './application/',
	'library_path' => './library/',
	'log_path' => "../log/",
	'cache_path' => "../cache/",
	'cache' => 0,
	'debug' => 1,
	'old_graphviz' => 0, #Set this to 1 if graphviz version < 2.24

	'setupfiles' => array(),
);

if (php_sapi_name() != 'cli') {
	ini_set('zlib.output_compression',1);
}

//config.php will allow you to override any set globals 
if(file_exists(getcwd().'/../config.php')) { 
	include_once(getcwd()."/../config.php"); 
} elseif(file_exists(getcwd().'/config.php')) { 
	include_once(getcwd()."/config.php"); 
} elseif(file_exists($nodeViz_config['application_path'].'/config.php')) { 
	include_once($nodeViz_config['application_path']."/config.php"); 
}

function reinterpret_paths() {
	global $nodeViz_config;
	foreach(array('application_path', 'log_path', 'cache_path') as $path) {
		$nodeViz_config[$path] = preg_replace("|^$nodeViz_config[web_path]|", '', $nodeViz_config[$path]) ;
	}
}

function setupHeaders() {
	$known = array('msie', 'firefox', 'safari', 'webkit', 'opera', 'netscape', 'konqueror', 'gecko');
	preg_match_all( '#(?<browser>' . join('|', $known) .  ')[/ ]+(?<version>[0-9]+(?:\.[0-9]+)?)#', strtolower( $_SERVER[ 'HTTP_USER_AGENT' ]), $browser );
	$svgdoctype = "";
	if (isset($browser['browser']) && isset($browser['browser'][0]) && $browser['browser'][0] == 'msie') { 
		header("content-type:text/html");
	} else {
		header("content-type:application/xhtml+xml");
		$svgdoctype = 'xmlns:svg="http://www.w3.org/2000/svg"';
	}
	$baseurl = "http://".preg_replace("/\/[^\/]*$/", "/", $_SERVER['HTTP_HOST']. $_SERVER['PHP_SELF']);
	return $svgdoctype;
}

//writelog: writes string out to logfile
function writelog($string, $loglevel = 1) {
	global $nodeViz_config, $logfile;
	if ($loglevel <= $nodeViz_config['debug']) { 
		$logdir = $nodeViz_config['nodeViz_path']."/".$nodeViz_config['log_path'];
		if (!$logfile) {  //open logfile if it isn't open
			$logfilename = "$logdir/".basename($_SERVER['PHP_SELF']).".log";
			$logfile= fopen($logfilename, 'a'); 
			if (! $logfile) {
				trigger_error("Unable to write log to log directory '$logdir'", E_USER_ERROR);
			}
		}
		fwrite($logfile, date('M d H:i:s', time())." - ".number_format(memory_get_usage())." - $string\n");
	}
}

/* below are mostly non-essential functions usefull for many apps */

function niceName($name, $lastfirst = 0) { 
	$titles	= array('dr','miss','mr','mrs','ms','judge', 'rep', 'sen', 'md', 'phd', 'hon', 'honorable', 'senator');
	$suffices = array('esq','esquire','jr','sr','2', 'ii','iii','iv');
	if (! strpos($name, ", ")) { 
		if(! strpos($name, " ")) { 
			return strtoupper($name);
		} else {
			return ucwords(strtolower($name)); 
		}
	}
	list($lastname, $fname) = explode(', ', $name, 2);

	$newname = array();
	$suff = "";
	foreach (explode(' ', $fname) as $part) {
		foreach ($titles as $title) {
			if (preg_match("/^$title\.?,?$/i", $part)) { continue 2; }
		}
		foreach ($suffices as $suffix) {
			if (preg_match("/^($suffix)\.?,?$/i",  $part, $matches)) { 
				if (preg_match("/^i[iv]*$/i", $matches[1])) { 
					$suff = strtoupper($matches[1]);
				} else {
					$suff = ucwords(strtolower($matches[1])).".";
				}
				continue 2;
			}
		} 
		if (strlen($part) == 1) { $part .= "."; }
		$part = preg_replace("/^([\"'\(]?)(.*)([\"'\)]?)$/e", '"$1".ucwords(strtolower("$2"))."$3"', $part); 
		$part = trim(str_replace("\'", "'", $part));
		if ($part != '') { 
			$newname[] = $part;
		}
	}
	$lastname = ucwords(strtolower($lastname));
	$lastname = preg_replace('/^mc(.)/ie', "'Mc'.strtoupper('$1')", $lastname);
	$lastname = preg_replace('/^(.*)-(.*)$/ie', "ucwords(strtolower('$1')).'-'.ucwords(strtolower('$2'))", $lastname);
	#if ($suff != '') { $lastname .= " $suff"; }
	if($lastfirst) { 
		return trim($lastname). ", ".join(' ', $newname). " ".trim($suff);
	} else {
		return join(' ', $newname). " ".trim($lastname). " ".trim($suff);
	}
}

function array_merge_recursive_unique($array0, $array1) {
    $arrays = func_get_args();
    $remains = $arrays;

    // We walk through each arrays and put value in the results (without
    // considering previous value).
    $result = array();

    // loop available array
    foreach($arrays as $array) {

        // The first remaining array is $array. We are processing it. So
        // we remove it from remaing arrays.
        array_shift($remains);

        // We don't care non array param, like array_merge since PHP 5.0.
        if(is_array($array)) {
            // Loop values
            foreach($array as $key => $value) {
                if(is_array($value)) {
                    // we gather all remaining arrays that have such key available
                    $args = array();
                    foreach($remains as $remain) {
                        if(array_key_exists($key, $remain)) {
                            array_push($args, $remain[$key]);
                        }
                    }

                    if(count($args) > 2) {
                        // put the recursion
                        $result[$key] = call_user_func_array(__FUNCTION__, $args);
                    } else {
                        foreach($value as $vkey => $vval) {
                            $result[$key][$vkey] = $vval;
                        }
                    }
                } else {
                    // simply put the value
                    $result[$key] = $value;
                }
            }
        }
    }
    return $result;
}

//zaps the pesking single and double quotes that may be in a string and mess things up
function cleanQuotes($string){
  $string = str_replace("'","",$string);
  $string = str_replace('"',"",$string);
  return $string;
}

//convert wierd stuff to html enttities
//in addition convert the code for ' into \' to get around problems
function safeLabel($string){
   $string = htmlentities($string,ENT_QUOTES);
   $string =str_replace('&#039;',"\'",$string);
    return $string;
}

//tries to return integer zoom values scaled appropriately for zoom levels
function scaleValueToZoom($valueMax,$valueMin,$value){
	$range = log($valueMax) - log($valueMin);
	$relative = (log($value)-log($valueMin))/$range;
	return round(9*$relative);
}

//interpolate colors between values for scaling
//TODO: Alpha?
function scaleValueToColor($valueMax,$valueMin,$value,$colorStartHex,$colorEndHex){
	$valRange = $valueMax -$valueMin;
	$valFraction = ($value-$valueMin)/$valRange;
	$startRGB = html2rgb($colorStartHex);
	$endRGB = html2rgb($colorEndHex);
	$red = $startRGB[0]+(($endRGB[0]-$startRGB[0]) * $valFraction);
	$green = $startRGB[1]+(($endRGB[1]-$startRGB[1]) * $valFraction);
	$blue = $startRGB[2]+(($endRGB[2]-$startRGB[2]) * $valFraction);
	$newCol = rgb2html($red,$green,$blue);
	return $newCol;

}

//from http://www.anyexample.com/programming/php/php_convert_rgb_from_to_html_hex_color.xml
function html2rgb($color)
{
    if ($color[0] == '#')
        $color = substr($color, 1);

    if (strlen($color) == 6)
        list($r, $g, $b) = array($color[0].$color[1],
                                 $color[2].$color[3],
                                 $color[4].$color[5]);
    elseif (strlen($color) == 3)
        list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
    else
        return false;

    $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

    return array($r, $g, $b);
}

//From http://www.anyexample.com/programming/php/php_convert_rgb_from_to_html_hex_color.xml
function rgb2html($r, $g=-1, $b=-1)
{
    if (is_array($r) && sizeof($r) == 3)
        list($r, $g, $b) = $r;

    $r = intval($r); $g = intval($g);
    $b = intval($b);

    $r = dechex($r<0?0:($r>255?255:$r));
    $g = dechex($g<0?0:($g>255?255:$g));
    $b = dechex($b<0?0:($b>255?255:$b));

    $color = (strlen($r) < 2?'0':'').$r;
    $color .= (strlen($g) < 2?'0':'').$g;
    $color .= (strlen($b) < 2?'0':'').$b;
    return '#'.$color;
}

//generate an array of hex colors to be mapped to values
//based on discussion here: http://www.krazydad.com/makecolors.php
function makeColorMap($values){
	$numCols = count($values);
	$center = 128;
	$width = 127;
	$frequency = 1;
	$phaseR = 0;
	$phaseG = 2;
	$phaseB = 4;
	$c=0;
	$colArray = array();
	while ($c < $numCols){
		$red = sin($frequency*$c+$phaseR) * $width + $center;
		$green = sin($frequency*$c+$phaseG) * $width + $center;
		$blue = sin($frequency*$c+$phaseB) * $width + $center;
		$colArray[$values[$c]] = rgb2html($red,$green,$blue);
		$c = $c+1;
	}
	return $colArray;
}

//Make a number of common abbreviations for this dataset to try to shorten labels
	function shortenLabel($origLabel){
		$origLabel = str_replace("Foundation","Fdn.",$origLabel);
		$origLabel = str_replace("Center","Ctr.",$origLabel);
		$origLabel = str_replace("Institute","Inst.",$origLabel);
		$origLabel = str_replace("University","U.",$origLabel);
		$origLabel = str_replace("Society","Soc.",$origLabel);
		$origLabel = str_replace("Association","Assn.",$origLabel);
		$origLabel = str_replace("National","Nat.",$origLabel);
		$origLabel = str_replace("International","Intl.",$origLabel);
		$origLabel = str_replace("Corporation","Corp.",$origLabel);
		$origLabel = str_replace("Incorporated","Inc.",$origLabel);
		$origLabel = str_replace("Department","Dept.",$origLabel);
		$origLabel = str_replace("District","Dist.",$origLabel);
		$origLabel = str_replace("Museum","Mus.",$origLabel);
		$origLabel = str_replace("Government","Govt.",$origLabel);
		$origLabel = str_replace(", The","",$origLabel);
		$origLabel = str_replace(", Inc.","",$origLabel);
		return $origLabel;
	}

//makes numbers shorter by only keeping significant digits
function formatHumanSuffix($number,$fullLabel){
	$codes = array('1'=>"",'1000'=>'K','1000000'=>'M','1000000000'=>'B','1000000000000'=>'T');
	if ($fullLabel){
		$codes = array('1'=>"",'1000'=>' Thousand','1000000'=>' Million','1000000000'=>'  Billion','1000000000000'=>' Trillion');
	}
	$divisor = 1;
	$suffix = "";
	foreach(array_keys($codes) as $div){
		if ($number > $div){
			$divisor = $div;
			$suffix = $codes[$div];
		} else {break;}
	}
	if ($suffix != ""){
		$number=$number/$divisor;
		$number=number_format($number,1).$suffix;
	}
	return $number;
}

/**
Returns JSON encoded object representation of its argument. 
This is a hack to do JSON encoding when running on PHP < 5.3 which does have the FORCE_OBJECTS flag http://www.php.net/manual/en/function.json-encode.php#100835
**/
function __json_encode( $data ) {           
    if( is_array($data) || is_object($data) ) {
        $islist = is_array($data) && ( empty($data) || array_keys($data) === range(0,count($data)-1) );
       
        if( $islist ) {
           // $json = '[' . implode(',', array_map('__json_encode', $data) ) . ']';
            $items = Array();
            $index = 0;
            foreach ($data as $value) {
            		$items[] = '"'.$index.'":'.__json_encode($value);
            		$index ++;
            }
            $json = '{' . implode(',', $items) . '}';
        } else {
            $items = Array();
            foreach( $data as $key => $value ) {
                $items[] = __json_encode("$key") . ':' . __json_encode($value);
            }
            $json = '{' . implode(',', $items) . '}';
        }
    } elseif( is_string($data) ) {
        # Escape non-printable or Non-ASCII characters.
        # I also put the \\ character first, as suggested in comments on the 'addclashes' page.
        $string = '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"';
        $json    = '';
        $len    = strlen($string);
        # Convert UTF-8 to Hexadecimal Codepoints.
        for( $i = 0; $i < $len; $i++ ) {
           
            $char = $string[$i];
            $c1 = ord($char);
           
            # Single byte;
            if( $c1 <128 ) {
                $json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1);
                continue;
            }
           
            # Double byte
            $c2 = ord($string[++$i]);
            if ( ($c1 & 32) === 0 ) {
                $json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128);
                continue;
            }
           
            # Triple
            $c3 = ord($string[++$i]);
            if( ($c1 & 16) === 0 ) {
                $json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128));
                continue;
            }
               
            # Quadruple
            $c4 = ord($string[++$i]);
            if( ($c1 & 8 ) === 0 ) {
                $u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1;
           
                $w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3);
                $w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128);
                $json .= sprintf("\\u%04x\\u%04x", $w1, $w2);
            }
        }
    } else {
        # int, floats, bools, null
        $json = strtolower(var_export( $data, true ));
    }
    return $json;
} 

function debug($message, $level=1) {
	global $nodeViz_config;
	$debug = $nodeViz_config['debug'];
	if ($debug == $level) {
		file_put_contents('php://stderr', number_format(memory_get_usage())." - $message\n");
	}
}

//From http://www.actionscript.org/forums/showthread.php3?t=50746
function RGB_TO_HSV ($R, $G=-1, $B=-1)  // RGB Values:Number 0-255
{                                 // HSV Results:Number 0-1
	if ($G == -1) {
		if ($R[0] == '#') { $R = substr($R, 1); }
		list($R, $G, $B) = array(hexdec($R[0].$R[1]), hexdec($R[2].$R[3]), hexdec($R[4].$R[5]));
	}

   $HSL = array();

   $var_R = ($R / 255);
   $var_G = ($G / 255);
   $var_B = ($B / 255);

   $var_Min = min($var_R, $var_G, $var_B);
   $var_Max = max($var_R, $var_G, $var_B);
   $del_Max = $var_Max - $var_Min;

   $V = $var_Max;
  $H = 0;
  $S = 0;

   if ($del_Max == 0) {
      $H = 0;
      $S = 0;
   } else {
      $S = $del_Max / $var_Max;

      $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
      $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

      if      ($var_R == $var_Max) $H = $del_B - $del_G;
      else if ($var_G == $var_Max) $H = ( 1 / 3 ) + $del_R - $del_B;
      else if ($var_B == $var_Max) $H = ( 2 / 3 ) + $del_G - $del_R;

      if ($H<0) $H++;
      if ($H>1) $H--;
   }

   $HSL['H'] = $H;
   $HSL['S'] = $S;
   $HSL['V'] = $V;

   return $HSL;
}

//From http://www.actionscript.org/forums/showthread.php3?t=50746
function HSV_TO_RGB ($H, $S=-1, $V=-1)  // HSV Values:Number 0-1
{                                 // RGB Results:Number 0-255
    $RGB = array();

    if($S == 0)
    {
        $R = $G = $B = $V * 255;
    }
    else
    {
        $var_H = $H * 6;
        $var_i = floor( $var_H );
        $var_1 = $V * ( 1 - $S );
        $var_2 = $V * ( 1 - $S * ( $var_H - $var_i ) );
        $var_3 = $V * ( 1 - $S * (1 - ( $var_H - $var_i ) ) );

        if       ($var_i == 0) { $var_R = $V     ; $var_G = $var_3  ; $var_B = $var_1 ; }
        else if  ($var_i == 1) { $var_R = $var_2 ; $var_G = $V      ; $var_B = $var_1 ; }
        else if  ($var_i == 2) { $var_R = $var_1 ; $var_G = $V      ; $var_B = $var_3 ; }
        else if  ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $V     ; }
        else if  ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $V     ; }
        else                   { $var_R = $V     ; $var_G = $var_1  ; $var_B = $var_2 ; }

        $R = $var_R * 255;
        $G = $var_G * 255;
        $B = $var_B * 255;
    }

    $RGB['R'] = $R;
    $RGB['G'] = $G;
    $RGB['B'] = $B;

    return $RGB;
} 
