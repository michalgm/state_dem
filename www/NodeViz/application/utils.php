<?php 

function niceName($name, $lastfirst = 0) { 
	$titles	= array('dr','miss','mr','mrs','ms','judge', 'rep', 'sen', 'md', 'phd', 'hon', 'honorable', 'senator');
	$suffices = array('esq','esquire','jr','sr','2', 'ii','iii','iv');
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
		$part = preg_replace_callback("/^([\"'\(]?)(.*)([\"'\)]?)$/e", '"$1".ucwords(strtolower("$2"))."$3"', $part); 
		$part = trim(str_replace("\'", "'", $part));
		if ($part != '') { 
			$newname[] = $part;
		}
	}
	$lastname = ucwords(strtolower($lastname));
	$lastname = preg_replace_callback('/^mc(.)/ie', "'Mc'.strtoupper('$1')", $lastname);
	$lastname = preg_replace_callback('/^(.*)-(.*)$/ie', "ucwords(strtolower('$1')).'-'.ucwords(strtolower('$2'))", $lastname);
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

?>
