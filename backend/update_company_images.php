<?php
include_once('../config.php');
include "../oc_utils.php";
chdir("../");
$pics_dir = "www/com_images/";
$force = isset($argv[1]);
if ($force) {
	system("rm -rf $pics_dir/*");
}
foreach (scandir("./backend/com_originals/") as $image) { 
	if (in_array($image, array('..', '.', 'convertimages.sh', 'duds', 'circle.png'))) { continue; }
	$path_info = pathinfo($image);
	$id = $path_info['filename'];
	if (! file_exists($pics_dir."c".$id.".png")) {
		print " $id ";
		createThumbnails("./backend/com_originals/$image", 'com');
	}
}
