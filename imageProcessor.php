<?php
//error_reporting(0);

require_once "imageProcessor.class.php";
require_once "S3.class.php";

if(!isset($_POST['data']) || empty($_POST['data'])) {
	$error_Arr[] = "Empty post information";
	processCease();
}

$data = $_POST['data'];

$error_Arr = [];
$success_Arr = [];

$processMyImage = new imageProcessor('tmp');

if(isset($data['s3']['access_key']) && isset($data['s3']['secret_key']) && isset($data['s3']['bucket']) && isset($data['s3']['path'])) {
	$access_key = $data['s3']['access_key'];
	$secret_key = $data['s3']['secret_key'];
	$bucketName = $data['s3']['bucket'];
	$path = $data['s3']['path'] ."/";
} else {
	$error_Arr[] = "Invalid S3 credentials";
}


if(isset($data['image_url'])) {
	if(!is_array($data['image_url']) && !empty(trim($data['image_url']))) {
		processCommence($data['image_url']);
	} else {
		foreach ($data['image_url'] as $key => $image_url) {
			if(!is_array($image_url) && !empty(trim($image_url))) {
				processCommence($image_url);
			} else {
				$error_Arr[] = "Invalid image URL format : $image_url";
			}
		}
	}	
} else {
	$error_Arr[] = "Image URL is empty";
}

processCease();

//<---FUNCTION BLOCK

//End image processing and print results
function processCease() {
	global $error_Arr;
	global $success_Arr;

	$return_Arr = [];

	if(count($error_Arr) > 0) {
		$return_Arr['error_msg'] = $error_Arr;
	}

	if(count($success_Arr) > 0) {
		$return_Arr['success_msg'] = $success_Arr;
	} 

	exit(json_encode($return_Arr));
}

//Commence image processing
function processCommence($imageURL) {
	global $error_Arr;
	global $success_Arr;
	global $processMyImage;
	global $data;
	global $access_key, $secret_key, $bucketName, $path;

	try {
		$processMyImage->load($imageURL);
		if(isset($data['image_process']) && count($data['image_process'])>0) {
			imageProcessEvents($data['image_process']);
		}
		$processMyImage->save();

		$uploadFile = "tmp/" . basename($imageURL);

		if (!extension_loaded('curl') && !@dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll')) {
			$error_Arr[] = "ERROR: CURL extension not loaded";
		}

		$s3 = new S3($access_key, $secret_key);

		if ($s3->putObjectFile($uploadFile, $bucketName, $path.baseName($uploadFile), S3::ACL_PUBLIC_READ)) {
			$success_Arr[] = $imageURL ." : Processed";
		} else {
			$error_Arr[] = $imageURL ." : Failed to copy file to S3";
		}

	} catch(Exception $e) {
		$error_Arr[] = $e->getMessage();
	}
	return true;
}

function imageProcessEvents($events) {
	global $processMyImage;
	global $error_Arr;

	//BLUR
	if(isset($events['blur'])) {
		if(count($events['blur']) ==1 && is_numeric($events['blur'][0])) {
			$processMyImage->blur("selective",$events['blur'][0]);
		} else {
			$error_Arr[] = "Blur definition error!";
		}
	}

	//BRIGHTNESS
	if(isset($events['brightness'])) {
		if(count($events['brightness']) ==1 && is_numeric($events['brightness'][0])) {
			$processMyImage->brightness($events['brightness'][0]);
		} else {
			$error_Arr[] = "Brightness definition error!";
		}
	}

	//CONTRAST
	if(isset($events['contrast'])) {
		if(count($events['contrast']) ==1 && is_numeric($events['contrast'][0])) {
			$processMyImage->contrast($events['contrast'][0]);
		} else {
			$error_Arr[] = "Contrast definition error!";
		}
	}

	//CROP
	if(isset($events['crop'])) {
		if(count($events['crop']) ==4 &&  ($events['crop'] === array_filter($events['crop'],'is_numeric'))) {
			$processMyImage->crop($events['crop'][0], $events['crop'][1], $events['crop'][2], $events['crop'][3]);
		} else {
			$error_Arr[] = "Crop definition error!";
		}
	}

	//FLIP
	if(isset($events['flip'])) {
		$flipPossiblities = ['x','y'];
		if(count($events['flip']) ==1 && in_array(strtolower($events['flip'][0]),$flipPossiblities)) {
			$processMyImage->flip(strtolower($events['flip'][0]));
		} else {
			$error_Arr[] = "Flip definition error!";
		}
	}

	//RESIZE
	if(isset($events['resize'])) {
		if(count($events['resize']) ==2 &&  ($events['resize'] === array_filter($events['resize'],'is_numeric'))) {
			$processMyImage->resize($events['resize'][0], $events['resize'][1]);
		} else {
			$error_Arr[] = "Resize definition error!";
		}
	}
}

//--->FUNCTION BLOCK
