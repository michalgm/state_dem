<?php
include_once('../config.php');
$candidates = dbLookupArray("select nimsp_candidate_id,url, photo_url, photo_url2, state, votesmart_id from legislators where (photo_url != '' || votesmart_id != '') and image = '' and nimsp_candidate_id != 0");
$bad_urls = array(); 
print "Need to fetch ".count($candidates)." missing candidate images\n";
print "V=Votesmart, 1=photo_url,2=photo_url2, C=colorado, .=not found\n";
foreach ($candidates as $leg) { 
	if ($leg['nimsp_candidate_id']) { 
		if (! copyImage($leg['photo_url'], $leg['nimsp_candidate_id'])) {
			if (strtoupper($leg['state']) == 'CO' && $leg['url']) {
				$page = file_get_contents($leg['url']);
				if (preg_match("/window.location.replace\('([^']*)'\)/", $page, $redir)) {
					$page = file_get_contents($redir[1]);
				}
				preg_match("/<img.+?src=\"([^\"]+jpg)\"/", $page, $matches);
				if (! isset($matches[1])) { print $page; exit; }
				$leg['photo_url'] = "http://www.leg.state.co.us//clics/clics2013A/directory.nsf/".$matches[1];
				dbwrite("update legislators set photo_url = '$leg[photo_url]' where nimsp_candidate_id = '$leg[nimsp_candidate_id]'");
				if( copyImage($leg['photo_url'], $leg['nimsp_candidate_id'])) { print "C"; continue;}
			}	
			if (! copyImage($leg['photo_url2'], $leg['nimsp_candidate_id'])) { 
				if ($leg['votesmart_id']) { 
				   	if (copyImage("http://votesmart.org/canphoto/$leg[votesmart_id].jpg", $leg['nimsp_candidate_id'])) {
						print "V"; 
					} else { 
						$bad_urls[] = "$leg[nimsp_candidate_id], $leg[photo_url], $leg[photo_url2], $leg[votesmart_id]";
						print "!";
					}
				}
			} else { 
				print "2";
			}	
		} else {
			print "1";
		}	
	} else {
		print ".";
	}
}
if ($bad_urls) { 
	print "Bad URLS found: \n";
	print_r($bad_urls);
}

function getExtensionFromURL($url) {
	$info = pathinfo($url); 
	$info['extension'] = isset($info['extension']) ? strtolower($info['extension']) : 'jpg';
	$extension = strtolower(preg_replace("/\?.*/", "", $info['extension']));
	$extension = $extension == 'jpeg' ? 'jpg' : $extension;	
	return $extension;
}

function copyImage($url, $id) {
	if(!$url) { return false; }
	$photo = "";
	if (file_exists("pics/$id.jpg")) { 
		$photo = "$id.jpg";
	} elseif( file_exists("pics/$id.png")) { 
		$photo = "$id.png";
	}

	if(! $photo) { 
		$extension = getExtensionFromURL($url);
		if($extension == 'nsf') { print "#"; return false; }
		if (@copy(str_replace(' ', '+', $url), "pics/$id.$extension")) { 
			$photo = "$id.$extension";
		}
	}
	if ($photo) { 
		dbwrite("update legislators set image='$photo' where nimsp_candidate_id = '$id'");
		return true;
	} else {
		return false;
	}
}

/*This was to fix broken CO image urls
$candidates = dbLookupArray("select nimsp_candidate_id, url, photo_url from legislators where photo_url != '' and state='CO'");
foreach ($candidates as $leg) { 
	$page = file_get_contents($leg['url']);
	preg_match("/<img.+?src=\"([^\"]+jpg)\"/", $page, $matches);
	$url="http://www.leg.state.co.us//clics/clics2013A/directory.nsf/".$matches[1];
	dbwrite("update legislators set photo_url = '$url' where nimsp_candidate_id = '$leg[nimsp_candidate_id]'");
}
exit;
 */
