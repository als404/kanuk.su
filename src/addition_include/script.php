<?php
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if ($contentType === "application/json") {
	$content = trim(file_get_contents("php://input"));
	$decoded = json_decode($content, true);

	if($decoded['fileExists']){
		$result['file'] = file_exists($_SERVER['DOCUMENT_ROOT'] . $decoded['fileExists']) ? true : false;
		
		echo json_encode($result);
	}
}
