<?php

// Function to sanitize input
function sanitize($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Initialize an array to store sanitized values
$parameter = [];

// List of allowed GET parameters
$allowed_params = [
    'download', 'bin_1', 'bin_2', 'color', 'alt_mode',
    'long_1', 'lat_1', 'height_1',
    'long_2', 'lat_2', 'height_2',
    'lon1_coef', 'lat1_coef', 'alt1_coef',
    'lon2_coef', 'lat2_coef', 'alt2_coef',
    'frequency', 'frequency_coefficient', 'zone',
    'display', 'fresnel',
    'input_address_1', 'input_install_1', 'input_address_2', 'input_install_2',
    'address_1', 'address_2',
    'height_radio_1', 'height_manual_1', 'height_radio_2', 'height_manual_2'
];

// Sanitize each allowed parameter
foreach ($allowed_params as $param) {
    if (isset($_GET[$param])) {
        $parameter[$param] = sanitize($_GET[$param]);
    } else {
        $parameter[$param] = ''; // Default empty value
    }
}

function hexToABGR($hex) {
    // Remove the '#' if present
    $hex = str_replace('#', '', $hex);

    // Convert hex to decimal values for ABGR
    $decimal = hexdec($hex);

    // Determine if alpha is included in the hex color
    if (strlen($hex) == 8) {
		// 8 characters indicates alpha is present
		$red = ($decimal >> 24) & 0xFF;
		$green = ($decimal >> 16) & 0xFF;
		$blue = ($decimal >> 8) & 0xFF;
		$alpha = dechex($decimal & 0xFF);
	} else {
		$red = ($decimal >> 16) & 0xFF;
		$green = ($decimal >> 8) & 0xFF;
		$blue = $decimal & 0xFF;
		$alpha = "ff";
	}

	// Pad hexadecimal components with leading zeros if necessary
	$redHex = str_pad(dechex($red), 2, '0', STR_PAD_LEFT);
	$greenHex = str_pad(dechex($green), 2, '0', STR_PAD_LEFT);
	$blueHex = str_pad(dechex($blue), 2, '0', STR_PAD_LEFT);
	$alphaHex = str_pad($alpha, 2, '0', STR_PAD_LEFT);

	// Return ABGR as hex
	return $alphaHex . $blueHex . $greenHex . $redHex;
}

// Credentials
$cred_header = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"Authorization: token " . getenv("MESHDB_KEY")
	)
);

$cred_context = stream_context_create($cred_header);


if (isset($parameter['download'])) {
	header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
	header("Content-Type: text/plain");
	header("Content-Disposition: attachment; filename=" . $parameter['bin_1'] . "-" . $parameter['bin_2'] . ".kml");
	echo('<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
	<name>' . $parameter['bin_1'] . "-" . $parameter['bin_2'] . '.kml</name>
        <Style id="inline">
                <LineStyle>
                        <color>' . hexToABGR($parameter['color']) . '</color>
                        <width>2</width>
                </LineStyle>
        </Style>
        <StyleMap id="inline0">
                <Pair>
                        <key>normal</key>
                        <styleUrl>#inline1</styleUrl>
                </Pair>
                <Pair>
                        <key>highlight</key>
                        <styleUrl>#inline</styleUrl>
                </Pair>
        </StyleMap>
        <Style id="inline1">
                <LineStyle>
                        <color>' . hexToABGR($parameter['color']) . '</color>
                        <width>2</width>
                </LineStyle>
        </Style>
        <Placemark>
                <name>' . $parameter['bin_1'] . "-" . $parameter['bin_2'] . '</name>
                <styleUrl>#inline0</styleUrl>
                <LineString>
                        <extrude>1</extrude>
                        <tessellate>1</tessellate>
                        <altitudeMode>' . $parameter['alt_mode'] . '</altitudeMode>
                        <coordinates>' . $parameter['long_1'] . ',' . $parameter['lat_1'] . ',' . $parameter['height_1'] . ' ' . $parameter['long_2'] . ',' . $parameter['lat_2'] . ',' . $parameter['height_2'] . '</coordinates>
                </LineString>
        </Placemark>
</Document>
</kml>');
die();
} elseif (isset($parameter['display'])) {
	$kmlContent = '<?xml version="1.0" encoding="UTF-8"?>
	<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
	<Document>
		<name>' . $parameter['bin_1'] . '-' . $parameter['bin_2'] . '.kml</name>
		<Style id="inline">
			<LineStyle>
				<color>' . hexToABGR($parameter['color']) . '</color>
				<width>2</width>
			</LineStyle>
		</Style>
		<StyleMap id="inline0">
			<Pair>
				<key>normal</key>
				<styleUrl>#inline1</styleUrl>
			</Pair>
			<Pair>
				<key>highlight</key>
				<styleUrl>#inline</styleUrl>
			</Pair>
		</StyleMap>
		<Style id="inline1">
			<LineStyle>
				<color>' . hexToABGR($parameter['color']) . '</color>
				<width>2</width>
			</LineStyle>
		</Style>
		<Placemark>
			<name>' . $parameter['bin_1'] . '-' . $parameter['bin_2'] . '</name>
			<styleUrl>#inline0</styleUrl>
			<LineString>
				<extrude>1</extrude>
				<tessellate>1</tessellate>
				<altitudeMode>relativeToGround</altitudeMode>
				<coordinates>' . $parameter['long_1'] . ',' . $parameter['lat_1'] . ',' . $parameter['height_1'] . ' ' . $parameter['long_2'] . ',' . $parameter['lat_2'] . ',' . $parameter['height_2'] . '</coordinates>
			</LineString>
		</Placemark>
	</Document>
	</kml>';

	// Create temporary file to hold KML content
	$tempKmlFile = tempnam(sys_get_temp_dir(), 'kml');
	file_put_contents($tempKmlFile, $kmlContent);

	// Prepare cURL request
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $_ENV["NYCKML_BE_URL"]); // Endpoint URL
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($tempKmlFile)]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	// Execute cURL request
	$response = curl_exec($ch);

	// Check for errors
	if(curl_errno($ch)) {
		echo 'Error: ' . curl_error($ch);
	} else {
		$imageData = base64_encode($response);
	}

	// Close cURL session
	curl_close($ch);

	// Delete temporary file
	unlink($tempKmlFile);
} elseif (isset($parameter['fresnel'])) {
    // Define the URL to submit the form
    $url = "https://www.radiofresnel.com/";

    // Define the payload with the values you want to submit
    $data = [
        "name1" => $parameter['bin_1'],
        "lon1" => $parameter['long_1'],
        "lon1_coef" => $parameter['lon1_coef'] ?? 1,
        "lat1" => $parameter['lat_1'],
        "lat1_coef" => $parameter['lat1_coef'] ?? 1,
        "altitude1" => round($parameter['height_1']),
        "alt1_coef" => $parameter['alt1_coef'] ?? 1,
        "name2" => $parameter['bin_2'],
        "lon2" => $parameter['long_2'],
        "lon2_coef" => $parameter['lon2_coef'] ?? 1,
        "lat2" => $parameter['lat_2'],
        "lat2_coef" => $parameter['lat2_coef'] ?? 1,
        "altitude2" => round($parameter['height_2']),
        "alt2_coef" => $parameter['alt2_coef'] ?? 1,
        "frequency" => $parameter['frequency'] ?? 5710,
        "frequency_coefficient" => $parameter['frequency_coefficient'] ?? 1,
        "zone" => $parameter['zone'] ?? 2.0,
        "colour" => $parameter['color'] . "ff",
        "my-form" => "Get kml"
    ];

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the POST request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL session
    curl_close($ch);

    // Check if the request was successful
    if ($http_code == 200) {
        // Set headers to download the KML file
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Content-Type: application/vnd.google-earth.kml+xml");
        header("Content-Disposition: attachment; filename=" . $data['name1'] . "-" . $data['name2'] . ".kml");

        // Output the response as a KML file
        echo str_replace("absolute", $parameter['alt_mode'], $response);
		die();
    } else {
        // If the request failed, output an error message
        echo "Failed to submit the form. HTTP Status Code: " . $http_code;
        echo "Response: " . htmlspecialchars($response);
    }
}

?>

<html>

<head>
<title>NYC Building KML Tool</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>
<link rel="stylesheet" href="nyckml.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css"/>
<script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js"></script>
<script src="nyckml.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>

<h1>NYC Building KML Tool</h1>

<form method="get">

<p class="input_fields">Enter the first address: <input name="input_address_1" value="<?php if(isset($parameter['input_address_1'])) {echo($parameter['input_address_1']);} ?>"> or an install number: <input name="input_install_1" value="<?php if(isset($parameter['input_install_1'])) {echo($parameter['input_install_1']);} ?>"></p>
<p class="input_fields">Enter the second address: <input name="input_address_2" value="<?php if(isset($parameter['input_address_2'])) {echo($parameter['input_address_2']);} ?>"> or an install number: <input name="input_install_2" value="<?php if(isset($parameter['input_install_2'])) {echo($parameter['input_install_2']);} ?>"></p>
<p class="input_fields"><button type="submit">Next</button></p>

<?php

// Input validation
if (isset($parameter['input_address_1'])) {
	$endpoints_fail = False;
	// Throw an error if there are no entries for one of the endpoints.
	if ((empty($parameter['input_address_1']) && empty($parameter['input_install_1'])) || (empty($parameter['input_address_2']) && empty($parameter['input_install_2']))) {
		echo ("Not sure how you expect me to do anything without either an address or install number for each endpoint. Enter something!");
		$endpoints_fail = True;
	}
	// Throw an error if there are two entries for one of the endpoints.
	if ((!empty($parameter['input_address_1']) && !empty($parameter['input_install_1'])) || (!empty($parameter['input_address_2']) && !empty($parameter['input_install_2']))) {
		echo ("Not sure how you expect me to do anything with both an address or install number for an endpoint. Pick one!");
		$endpoints_fail = True;
	}

	if (!$endpoints_fail) {
		// Turn off the input fields and display what the user entered.
		echo ("<style>.input_fields {display:none} .height_fields {display:block}</style>");
		// Endpoint 1 (get vars from GET if we already have it)
		if (isset($parameter['address_1'])) {
			$address_1 = $parameter['address_1'];
			$bin_1 = $parameter['bin_1'];
			$lat_1 = $parameter['lat_1'];
			$long_1 = $parameter['long_1'];
		} else {
			if (empty($parameter['input_address_1'])) {
				// Get variables from db
				echo ("<p>For endpoint #1, you entered install number: " . $parameter['input_install_1'] . "</p>");
				$install_get = file_get_contents("https://db.nycmesh.net/api/v1/installs/" . $parameter['input_install_1']  . "/", false, $cred_context);
				$install_json = json_decode($install_get);
				$building_get = file_get_contents("https://db.nycmesh.net/api/v1/buildings/" . $install_json->building->id  . "/", false, $cred_context);
				$building_json = json_decode($building_get);
				$address_1 = $building_json->street_address . ", " . $building_json->city . ", " . $building_json->state . ", " . $building_json->zip_code;
				$bin_1 = $building_json->bin;
				$lat_1 = $building_json->latitude;
				$long_1 = $building_json->longitude;
			} else {
				// Get variables from DCP dataset
				echo ("<p>For endpoint #1, you entered address: " . $parameter['input_address_1'] . "</p>");
				$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($parameter['input_address_1']));
				$address_1 = json_decode($dcp_get, true)['features']['0']['properties']['label'];
				$bin_1 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
				$lat_1 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["1"];
				$long_1 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["0"];
			}
		}
		echo("<p>Matched Address: " . $address_1 . "</p><input type='hidden' name='address_1' value='" . $address_1 . "'>");
		echo("<p>BIN: " . $bin_1 . "</p><input type='hidden' name='bin_1' value='" . $bin_1 . "'>");
		echo("<p>Coordinates: <span id='coords_1'>" . $lat_1 . ", " . $long_1 . "</span> <a id='map_1_reset' class='reset' onclick='map_1_reset()' href='#'>(reset)</a></p>");
		if (empty($parameter['lat_1'])) {
			echo("<div id='map_1' class='map'></div>");
		}
		echo("<input id='lat_1' type='hidden' name='lat_1' value='" . $lat_1 . "'></div>");
		echo("<input id='long_1' type='hidden' name='long_1' value='" . $long_1 . "'></div>");

		// Endpoint 2 (get vars from GET if we already have it)
                if (isset($parameter['address_2'])) {
                        $address_2 = $parameter['address_2'];
                        $bin_2 = $parameter['bin_2'];
                        $lat_2 = $parameter['lat_2'];
                        $long_2 = $parameter['long_2'];
                } else {
			if (empty($parameter['input_address_2'])) {
				// Get variables from spreadsheet
				echo ("<p>For endpoint #2, you entered install number: " . $parameter['input_install_2'] . "</p>");
				$install_get = file_get_contents("https://db.nycmesh.net/api/v1/installs/" . $parameter['input_install_2']  . "/", false, $cred_context);
				$install_json = json_decode($install_get);
				$building_get = file_get_contents("https://db.nycmesh.net/api/v1/buildings/" . $install_json->building->id  . "/", false, $cred_context);
				$building_json = json_decode($building_get);
				$address_2 = $building_json->street_address . ", " . $building_json->city . ", " . $building_json->state . ", " . $building_json->zip_code;
				$bin_2 = $building_json->bin;
				$lat_2 = $building_json->latitude;
				$long_2 = $building_json->longitude;
			} else {
				// Get variables from DCP dataset
				echo ("<p>For endpoint #2, you entered address: " . $parameter['input_address_2'] . "</p>");
                        	$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($parameter['input_address_2']));
                        	$address_2 = json_decode($dcp_get, true)['features']['0']['properties']['label'];
                        	$bin_2 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
                        	$lat_2 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["1"];
                        	$long_2 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["0"];
			}
		}
                echo("<p>Matched Address: " . $address_2 . "</p><input type='hidden' name='address_2' value='" . $address_2 . "'>");
                echo("<p>BIN: " . $bin_2 . "</p><input type='hidden' name='bin_2' value='" . $bin_2 . "'>");
                echo("<p>Coordinates: <span id='coords_2'>" . $lat_2 . ", " . $long_2 . "</span> <a id='map_2_reset' class='reset' onclick='map_2_reset()' href='#'>(reset)</a></p>");
                if (empty($parameter['lat_2'])) {
                        echo("<div id='map_2' class='map'></div>");
		}
                echo("<input id='lat_2' type='hidden' name='lat_2' value='" . $lat_2 . "'></div>");
                echo("<input id='long_2' type='hidden' name='long_2' value='" . $long_2 . "'></div>");
	}
}

?>

<p class='height_fields'>For endpoint #1, where do you want to get the height from?
<input type='radio' id='dob1' name='height_radio_1' value='dob' <?php if(isset($parameter['height_radio_1'])){if($parameter['height_radio_1']=='dob'){echo('checked');}} ?>>
<label for='dob1'>DOB</label>
<input type='radio' id='spreadsheet1' name='height_radio_1' value='spreadsheet' <?php if(isset($parameter['height_radio_1'])){if($parameter['height_radio_1']=='spreadsheet'){echo('checked');}} ?>>
<label for='spreadsheet'>DB</label>
<input type='number' name='height_manual_1' placeholder='Manual Entry (in meters)' value="<?php if(isset($parameter['height_manual_1'])) {echo($parameter['height_manual_1']);} ?>">
</p>

<p class='height_fields'>For endpoint #2, where do you want to get the height from?
<input type='radio' id='dob2' name='height_radio_2' value='dob' <?php if(isset($parameter['height_radio_2'])){if($parameter['height_radio_2']=='dob'){echo('checked');}} ?>>
<label for='dob2'>DOB</label>
<input type='radio' id='spreadsheet2' name='height_radio_2' value='spreadsheet' <?php if(isset($parameter['height_radio_2'])){if($parameter['height_radio_2']=='spreadsheet'){echo('checked');}} ?>>
<label for='spreadsheet2'>DB</label>
<input type='number' name='height_manual_2' placeholder='Manual Entry (in meters)' value="<?php if(isset($parameter['height_manual_2'])) {echo($parameter['height_manual_2']);} ?>">
</p>
<p class="height_fields"><button type="submit">Next</button></p>

<?php

// Input validation
if (isset($parameter['input_address_1'])) {
	// Throw an error if there are no entries for either of the endpoints.
	$heights_fail = False;
	if (empty($parameter['height_radio_1']) && empty($parameter['height_manual_1']) || empty($parameter['height_radio_2']) && empty($parameter['height_manual_2'])) {
		echo("Not sure how you expect me to do anything without knowing from where I should get the height. Pick something!");
		$heights_fail = True;
	}

	// Turn off the input fields and display what the user entered.
	if (!$heights_fail) {
		echo ("<style>.height_fields {display:none} .option_fields {display:block} .reset {display:none}</style>");
		// Endpoint 1
		echo("<p>Height of endpoint #1: ");
		if (empty($parameter['height_manual_1'])) {
			// Get height using BIN from DOB dataset
			if ($parameter['height_radio_1'] == 'dob') {
				$dob_get = file_get_contents('https://data.cityofnewyork.us/resource/5zhs-2jue.json?bin=' . $bin_1);
				$meters_1 = json_decode($dob_get, true)['0']['height_roof'] * 0.3048;
				$alt_mode_1 = "relativeToGround";
			}
			// Get height using cell in db
			if ($parameter['height_radio_1'] == 'spreadsheet') {
				// Use the Install # to query the db directly
				$alt_mode_1 = "absolute";
				if (empty($parameter['input_address_1'])) {
					$install_get = file_get_contents("https://db.nycmesh.net/api/v1/installs/" . $parameter['input_install_1']  . "/", false, $cred_context);
					$install_json = json_decode($install_get);
					$building_get = file_get_contents("https://db.nycmesh.net/api/v1/buildings/" . $install_json->building->id  . "/", false, $cred_context);
					$building_json = json_decode($building_get);
					$meters_1 = $building_json->altitude;
				} else {
					// Get BIN from DCP dataset
					$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($parameter['input_address_1']));
					$bin_1 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
					$url = "https://db.nycmesh.net/api/v1/buildings/lookup/?bin=" . $bin_1;
					$building_get = file_get_contents($url, false, $cred_context);
					$building_json = json_decode($building_get);
					$meters_1 = $building_json->results[0]->altitude;
				}
			}
		} else {
			// Use entered value for height
			$meters_1 = $parameter['height_manual_1'];
			$alt_mode_1 = "relativeToGround";
		}
		if (!$heights_fail) {
			echo($meters_1 . "m | " . $meters_1*3.28084 . "ft</p>");
            echo("<input type='hidden' name='height_1' value='" . $meters_1 . "'>");
            echo("<input type='hidden' name='alt_mode' value='" . $alt_mode_1 . "'>");
		}

		// Endpoint 2
		echo("<p>Height of endpoint #2: ");
			if (empty($parameter['height_manual_2'])) {
				// Get height using BIN from DOB dataset
				if ($parameter['height_radio_2'] == 'dob') {
					$dob_get = file_get_contents('https://data.cityofnewyork.us/resource/5zhs-2jue.json?bin=' . $bin_2);
					$meters_2 = json_decode($dob_get, true)['0']['height_roof'] * 0.3048;
					$alt_mode_2 = "relativeToGround";
				}
				// Get height using cell in db
				if ($parameter['height_radio_2'] == 'spreadsheet') {
					// Use the Install # to query the db directly
					$alt_mode_2 = "absolute";
					if (empty($parameter['input_address_2'])) {
						$install_get = file_get_contents("https://db.nycmesh.net/api/v1/installs/" . $parameter['input_install_2']  . "/", false, $cred_context);
						$install_json = json_decode($install_get);
						$building_get = file_get_contents("https://db.nycmesh.net/api/v1/buildings/" . $install_json->building->id  . "/", false, $cred_context);
						$building_json = json_decode($building_get);
						$meters_2 = $building_json->altitude;
					} else {
						// Get BIN from DCP dataset
						$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($parameter['input_address_2']));
						$bin_2 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
						$url = "https://db.nycmesh.net/api/v1/buildings/lookup/?bin=" . $bin_2;
						$building_get = file_get_contents($url, false, $cred_context);
						$building_json = json_decode($building_get);
						$meters_2 = $building_json->results[0]->altitude;
					}
				}
			} else {
				// Use entered value for height
				$meters_2 = $parameter['height_manual_2'];
				$alt_mode_2 = "relativeToGround";
			}
		if (!$heights_fail) {
			echo($meters_2 . "m | " . $meters_2*3.28084 . "ft</p>");
            echo("<input type='hidden' name='height_2' value='" . $meters_2 . "'>");
            echo("<input type='hidden' name='alt_mode' value='" . $alt_mode_2 . "'>");
		}
	}
}

?>

<p class="option_fields">I am ready to export the KML. Select the options you want and let's do this!</p>
<p class="option_fields">Line Color: <input type="text" id="color" name="color" value="<?php if (isset($parameter['color'])) { echo $parameter['color']; } ?>" data-coloris></p>
<input class="option_fields" type="submit" value="Download KML" name="download" />
<input class="option_fields" type="submit" value="Download Fresnel" name="fresnel" />
<input class="option_fields" type="submit" value="Display KML (beta)" name="display" />

</form>

<h2><button onclick="goBack();">Go Back</button><button onclick="window.location.href=window.location.origin + window.location.pathname">Start Over</button></h2>

<?php

if ($imageData) {
	echo '<img src="data:image/png;base64,' . $imageData. '" alt="Screenshot" style="width: 100%">';
}

?>
</body>

</html>
