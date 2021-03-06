<?php

$actionTimers = Array();
$actionLog = Array();

function AddActionLog($logEntry){
    global $actionLog;

	if(!isset($actionLog[$logEntry])){
		$actionLog[$logEntry] = 0;
	}
	$actionLog[$logEntry] += 1;
}

function StartTimer($timerName){
	global $actionTimers;

	if(!isset($actionTimers[$timerName])){
		$actionTimers[$timerName] = Array("totalTime" => 0, "timerRunning" => false, "lastTimestamp" => 0);
	}

	if($actionTimers[$timerName]["timerRunning"]){
		die("timer $timerName already running");
	}

	$actionTimers[$timerName]["timerRunning"] = true;
	$actionTimers[$timerName]["lastTimestamp"] = microtime(true);
}

function StopTimer($timerName){
	global $actionTimers;

	if(!isset($actionTimers[$timerName])){
		die("timer $timerName does not exist");
	}

	if($actionTimers[$timerName]["timerRunning"] == false){
		die("timer $timerName not running");
	}

	$actionTimers[$timerName]["timerRunning"] = false;
    $actionTimers[$timerName]["totalTime"] += microtime(true) - $actionTimers[$timerName]["lastTimestamp"];
    $actionTimers[$timerName]["timeInSeconds"] = number_format($actionTimers[$timerName]["totalTime"], 8);
}

function StartsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
	AddActionLog("StartsWith");
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

function EndsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
	AddActionLog("EndsWith");
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

//Numeric comparator for arrays based on property
function CmpArrayByPropertyPopularityNum($a, $b)
{
	AddActionLog("CmpArrayByPropertyPopularityNum");
	if(isset($a["banned"]) && $a["banned"] == 1){
		return 1;
	}
	return CmpArrayByProperty($a, $b, "popularity_num");
}

//Numeric comparator for arrays based on property
function CmpArrayByProperty($a, $b, $property)
{
	AddActionLog("CmpArrayByProperty");
	return $a[$property] < $b[$property];
}

//Returns ordinal version of provided number, so 1 -> 1st; 3 -> 3rd, etc.
function ordinal($number) {
	AddActionLog("ordinal");
	StartTimer("ordinal");
	$number = intval($number);
    $ends = array('th','st','nd','rd','th','th','th','th','th','th');
    if ((($number % 100) >= 11) && (($number%100) <= 13)){
		StopTimer("ordinal");
		return $number. 'th';
	}
    else{
		StopTimer("ordinal");
		return $number. $ends[$number % 10];
	}
	StopTimer("ordinal");
}

function GetNextJamDateAndTime(){
	global $dictionary, $config;
	AddActionLog("GetNextJamDateAndTime");
	StartTimer("GetNextJamDateAndTime");

    $jamDay = "monday";
    switch($config["JAM_DAY"]["VALUE"]){
        case 0:
            $jamDay = "sunday";
        break;
        case 1:
            $jamDay = "monday";
        break;
        case 2:
            $jamDay = "tuesday";
        break;
        case 3:
            $jamDay = "wednesday";
        break;
        case 4:
            $jamDay = "thursday";
        break;
        case 5:
            $jamDay = "friday";
        break;
        case 6:
            $jamDay = "saturday";
        break;
    }

	$suggestedDay = strtotime("$jamDay +" . intval($config["JAM_TIME"]["VALUE"]) . " hours UTC");
	$dictionary["next_jam_suggested_date"] = gmdate("Y-m-d", $suggestedDay);
	$dictionary["next_jam_suggested_time"] = gmdate("H:i", $suggestedDay);
	$now = time();
	$interval = $suggestedDay - $now;
	$dictionary["seconds_until_jam_suggested_time"] = $interval;

	StopTimer("GetNextJamDateAndTime");
	return $suggestedDay;
}

$currentJamNumberArchive = FALSE;
function GetCurrentJamNumberAndID(){
	global $currentJamNumberArchive, $dbConn;
	AddActionLog("GetCurrentJamNumberAndID");
	StartTimer("GetCurrentJamNumberAndID");

	if($currentJamNumberArchive !== FALSE){
		StopTimer("GetCurrentJamNumberAndID");
		return $currentJamNumberArchive;
	}

	$sql = "
		SELECT j.jam_id, j.jam_jam_number
		FROM (
			SELECT MAX(jam_id) as max_jam_id
			FROM jam
			WHERE jam_start_datetime <= Now()
			  AND jam_deleted = 0
		) past_jams, jam j
		WHERE past_jams.max_jam_id = j.jam_id
	";
	$data = mysqli_query($dbConn, $sql);
	$sql = "";

	if($info = mysqli_fetch_array($data)){
		$currentJamNumberArchive = Array("NUMBER" => intval($info["jam_jam_number"]), "ID" => intval($info["jam_id"]));
	}else{
		$currentJamNumberArchive = Array("NUMBER" => 0, "ID" => 0);
	}
	StopTimer("GetCurrentJamNumberAndID");
	return $currentJamNumberArchive;
}

// Uses a whitelist of tags and attributes, plus parses the HTML to ensure
// the markup is well-formed and limited to non-harming code.
function CleanHtml($html) {
	// Remove tags
	AddActionLog("CleanHtml");
	StartTimer("CleanHtml");

	$halfCleanedHtml = strip_tags($html, '<p><strong><em><strike><sup><sub><a><ul><li>');

	// Parse non-empty HTML only
	if (!empty(trim($html))) {
		$dom = new DOMDocument();
		$dom->loadHTML($halfCleanedHtml);

		// Only keep whitelisted HTML attributes
		$allowed_attributes = array('href');
		foreach ($dom->getElementsByTagName('*') as $node) {
		    for ($i = $node->attributes->length - 1; $i >= 0; $i--) {
		        $attribute = $node->attributes->item($i);
		        if (!in_array($attribute->name, $allowed_attributes)) {
		        	$node->removeAttributeNode($attribute);
		        }
		    }
		}

		// Stringify the DOMDocument <body> contents
		$cleanedHtml = '';
		foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
		    $cleanedHtml .= $dom->saveXML($node);
		}
		StopTimer("CleanHtml");
		return $cleanedHtml;
	} else {
		StopTimer("CleanHtml");
		return NULL;
	}
	StopTimer("CleanHtml");
}

// Converts a MySQL data object into an Array (Skips numeric keys aka $data[0], $data[1], etc.)
function MySQLDataToArray($data){
	AddActionLog("MySQLDataToArray");
	StartTimer("MySQLDataToArray");

	$result = Array();
	while($asset = mysqli_fetch_array($data)){
		$row = Array();
		foreach($asset as $key => $value){
			if(!is_numeric($key)){
				$row[$key] = $value;
			}
		}
		$result[] = $row;
	}

	StopTimer("MySQLDataToArray");
	return $result;
}

// Converts a two dimensional array into a html-formatted table (string output)
function ArrayToHTML($array){
	AddActionLog("ArrayToHTML");
	StartTimer("ArrayToHTML");

	if(count($array) == 0){
		StopTimer("ArrayToHTML");
		return "No data in table";
	}

	$str = "<table style='border: solid 1px'>";

	foreach($array as $id => $row){
		$str .= "<tr style='border: solid 2px'><td>#</td>";
		foreach($row as $key => $value){
			$str .= "<th style='border: solid 2px'>";
			$str .= "$key";
			$str .= "</th>";
		}
		$str .= "</tr>";
		break;
	}

	foreach($array as $id => $row){
		$str .= "<tr style='border: solid 1px'><td>$id</td>";
		foreach($row as $key => $value){
			$str .= "<td style='border: solid 1px'>";
			$str .= "$value";
			$str .= "</td>";
		}
		$str .= "</tr>";
	}

	$str .= "</table>";

	StopTimer("ArrayToHTML");
	return $str;
}

?>
