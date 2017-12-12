<?php
$file = "orders-[SHOP_NAME]-weebly-com-[START]-[END].csv";

date_default_timezone_set("EST");
$explain = true;
$catch = array("BILLING NAME", "DATE", "ORDER #", "SHIPPING COUNTRY", "SHIPPING FIRST NAME", "SHIPPING LAST NAME", 
	"SHIPPING ADDRESS", "SHIPPING ADDRESS 2", "SHIPPING POSTAL CODE", "SHIPPING CITY", "SHIPPING REGION", "SHIPPING COUNTRY", "PRODUCT NAME", "STATUS");
$catchIndex = array();
$catchNUM = array();
$list = array();
$row = 1;
$empty = 0;
$cancelled = 0;
$Tnum = 0;
define("DATE", "F j, Y @ g:i A");

// @link https://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
function iarray(){
	global $catchIndex, $catchNUM;
	return array_combine($catchIndex, $catchNUM);
}

// File Selector
	if( (!isset($file) || !file_exists($file)) && !isset($_GET['file'])){
		$file = "";
		$fs = glob("*.csv");
		
		usort($fs, function($a,$b){
		  return filemtime($a) - filemtime($b);
		});
		$fs = array_reverse($fs);
		
		$fslist = array();
		foreach($fs as $i => $name){
			$fslist[] = $name;
		}
		
		echo "<title>Please Select File</title>";
		echo "<style> @page{ margin: 10px; size: landscape; } @media print { a { text-decoration: none; color:black; } </style>";
		echo "<table>";
			echo "<tr><td style='width:480px;'>&nbsp;</td><td style='width:300px;'>&nbsp;</td><td><u><b>".date(DATE)."</b></u></td></tr>";
			foreach($fslist as $i => $fs){
				echo "<tr><td><a href='?file=".$fs."'>".$fs."</a></td><td>".md5_file($fs)."</td><td>" .time_elapsed_string('@'.filectime($fs), true). "</td></tr>";
			}
		echo "</table>";
		exit();
	}
	else if(isset($_GET['file']) && preg_match("/orders-([a-zA-Z0-9]{1,})-weebly-com-([0-9]{1,}|start)-([0-9]{1,})\.csv/", $_GET['file'])) {
		$file = $_GET['file'];
	}

// Process the filename for Times
	preg_match("/orders-([a-zA-Z0-9]{1,})-weebly-com-([0-9]{1,}|start)-([0-9]{1,})\.csv/", $file, $matches);
	if($matches[2] === "start"){
		$matches[2] = 0;
	}

// The Processing Section
// Loop the File to find the Records and the Data.
	if (file_exists($file) && ($handle = fopen($file, "r")) !== FALSE) {
		while ( ($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			$num = count($data);

			// Grab the Locations of the Columns
			if($row == 1){
				foreach($data as $k => $d){
					if(in_array(strtoupper($d), $catch)){
						$catchIndex[] = strtoupper($d);
						$catchNUM[] = $k;
					}
				}
			}
			else {
				$h = iarray();
				if( $data[$h['STATUS']] == "cancelled" ){
					$cancelled++;
				}
				else if( !empty( $data[ $h['SHIPPING REGION'] ] ) ){
					$list[] = array(
						"Name" => strtoupper( $data[$h['SHIPPING FIRST NAME']] ." ". $data[$h['SHIPPING LAST NAME']] ),
						"Date" => strtoupper( $data[$h['DATE']] ),
						"OrderNum" => strtoupper( $data[$h['ORDER #']] ),
						"Address" => array(
							1 => $data[$h['SHIPPING ADDRESS']],
							2 => $data[$h['SHIPPING ADDRESS 2']],
							3 => $data[$h['SHIPPING CITY']] .", ". $data[$h['SHIPPING REGION']] ." ". $data[$h['SHIPPING POSTAL CODE']],
						)
					);
				}
				else {
					$empty++;
				}
			}
			$row++;
			$Tnum += $num;
		}
		fclose($handle);
	}
	else {
		http_response_code(500);
		echo "<title>Not Found!</title>";
		echo "File not Found!<br>";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;".$file;
		exit();
	}


// Loop the Records
	$records = array();
	foreach($list as $i => $k){
		$s = $k;
		if($s['Address'][1] == $s['Address'][2]){
			$s['Address'][2] = "";
		}
		$records[$s['Name']] = $s;
	}
	
// Print the Header File Data
	echo "<title>Export of: ".$file."</title>";
	echo "<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=yes'>";
	echo "<style> @page{ margin: 10px; } </style>";
	$re = array(
		"Date From: " => date(DATE, $matches[2]),
		"Date To: " => date(DATE, $matches[3]),
		"Total Processed Rows of Data:" => count($list),
		"Total Address in Data:" => count($records),
		"&nbsp;" => "&nbsp;",
		"<small>File Name:</small>" => "<small>".$file."</small>",
		"<small>Date of Index Creation:</small>" => "<small>".date(DATE)."</small>",
		"<small>File Creation Time:</small>" => "<small>".date(DATE, filectime($file))." (". time_elapsed_string('@'.filectime($file)) .")</small>",
		"<small>File Hash:</small>" => "<small>".md5_file($file)."</small>",
		"<small>Rows considered to be Empty:</small>" => "<small>".$empty."</small>",
		"<small>Cancelled Orders:</small>" => "<small>".$cancelled."</small>",
		"<small>Total Read Calls:</small>" => "<small>".$Tnum."</small>",
	);
	echo "<table style='font-size:15px;'>";
	echo "<tr><td style='width:300px;'></td><td></td></tr>";
	foreach($re as $k => $s){
		echo "<tr>";
			echo "<td>".$k."</td><td><b>".$s."</b></td>";
		echo "</tr>";
	}
	echo "</table>";


// Print Records
	echo "<br>";
	foreach($records as $name => $r){
		echo "<br><br>";
		echo "<table style='padding-left:60px;font-family: monospace; font-size: 18px;'>";
			echo "<tr><td>".$r['Name']."</td></tr>";
			echo "<tr><td>".$r['Address'][1]."</td></tr>";
			echo "<tr><td>".$r['Address'][2]."</td></tr>";
			echo "<tr><td>".$r['Address'][3]."</td></tr>";
		echo "</table>";
		echo "<br><br>";
	}
// End of File.
?>