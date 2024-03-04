<?php

/*

Typed Address to Real Address, BINs, and Coords
https://geosearch.planninglabs.nyc/docs/

BIN to Height
https://dev.socrata.com/foundry/data.cityofnewyork.us/7w4b-tj9d

*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Credentials
$cred_header = array(
	'http'=>array(
		'method'=>"GET",
		'header'=>"Authorization: token "
	)
);

$cred_context = stream_context_create($cred_header);


if (isset($_GET['kml'])) {
	header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
	header("Content-Type: text/plain");
	header("Content-Disposition: attachment; filename=" . $_GET['bin_1'] . "-" . $_GET['bin_2'] . ".kml");
	echo('<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
	<name>' . $_GET['bin_1'] . "-" . $_GET['bin_2'] . '.kml</name>
        <Style id="inline">
                <LineStyle>
                        <color>' . $_GET['color'] . '</color>
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
                        <color>' . $_GET['color'] . '</color>
                        <width>2</width>
                </LineStyle>
        </Style>
        <Placemark>
                <name>' . $_GET['bin_1'] . "-" . $_GET['bin_1'] . '</name>
                <styleUrl>#inline0</styleUrl>
                <LineString>
                        <extrude>1</extrude>
                        <tessellate>1</tessellate>
                        <altitudeMode>' . $_GET['alt_mode'] . '</altitudeMode>
                        <coordinates>' . $_GET['long_1'] . ',' . $_GET['lat_1'] . ',' . $_GET['height_1'] . ' ' . $_GET['long_2'] . ',' . $_GET['lat_2'] . ',' . $_GET['height_2'] . '</coordinates>
                </LineString>
        </Placemark>
</Document>
</kml>');
die();
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
<link rel="stylesheet" href="/nyckml.css"/>
<script src="/nyckml.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>

<h1>NYC Building KML Tool</h1>
<h2><button onclick="window.location.href='/'">Spreadsheet Based</button><button disabled>DB Based</button></h2>

<form method="get">

<p class="input_fields">Enter the first address: <input name="input_address_1" value="<?php if(isset($_GET['input_address_1'])) {echo($_GET['input_address_1']);} ?>"> or an install number: <input name="input_install_1" value="<?php if(isset($_GET['input_install_1'])) {echo($_GET['input_install_1']);} ?>"></p>
<p class="input_fields">Enter the second address: <input name="input_address_2" value="<?php if(isset($_GET['input_address_2'])) {echo($_GET['input_address_2']);} ?>"> or an install number: <input name="input_install_2" value="<?php if(isset($_GET['input_install_2'])) {echo($_GET['input_install_2']);} ?>"></p>
<p class="input_fields"><button type="submit">Next</button></p>

<?php

// Input validation
if (isset($_GET['input_address_1'])) {
	$endpoints_fail = False;
	// Throw an error if there are no entries for one of the endpoints.
	if ((empty($_GET['input_address_1']) && empty($_GET['input_install_1'])) || (empty($_GET['input_address_2']) && empty($_GET['input_install_2']))) {
		echo ("Not sure how you expect me to do anything without either an address or install number for each endpoint. Enter something!");
		$endpoints_fail = True;
	}
	// Throw an error if there are two entries for one of the endpoints.
	if ((!empty($_GET['input_address_1']) && !empty($_GET['input_install_1'])) || (!empty($_GET['input_address_2']) && !empty($_GET['input_install_2']))) {
		echo ("Not sure how you expect me to do anything with both an address or install number for an endpoint. Pick one!");
		$endpoints_fail = True;
	}

	if (!$endpoints_fail) {
		// Turn off the input fields and display what the user entered.
		echo ("<style>.input_fields {display:none} .height_fields {display:block}</style>");
		// Endpoint 1 (get vars from GET if we already have it)
		if (isset($_GET['address_1'])) {
			$address_1 = $_GET['address_1'];
			$bin_1 = $_GET['bin_1'];
			$lat_1 = $_GET['lat_1'];
			$long_1 = $_GET['long_1'];
		} else {
			if (empty($_GET['input_address_1'])) {
				// Get variables from db
				echo ("<p>For endpoint #1, you entered install number: " . $_GET['input_install_1'] . "</p>");
				$install_get = file_get_contents("http://db.grandsvc.mesh/api/v1/installs/" . $_GET['input_install_1']  . "/", false, $cred_context);
				$install_json = json_decode($install_get);
				$building_get = file_get_contents("http://db.grandsvc.mesh/api/v1/buildings/" . $install_json->building  . "/", false, $cred_context);
				$building_json = json_decode($building_get);
				$address_1 = $building_json->street_address . ", " . $building_json->city . ", " . $building_json->state . ", " . $building_json->zip_code;
				$bin_1 = $building_json->bin;
				$lat_1 = $building_json->latitude;
				$long_1 = $building_json->longitude;
			} else {
				// Get variables from DCP dataset
				echo ("<p>For endpoint #1, you entered address: " . $_GET['input_address_1'] . "</p>");
				$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($_GET['input_address_1']));
				$address_1 = json_decode($dcp_get, true)['features']['0']['properties']['label'];
				$bin_1 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
				$lat_1 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["1"];
				$long_1 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["0"];
			}
		}
		echo("<p>Matched Address: " . $address_1 . "</p><input type='hidden' name='address_1' value='" . $address_1 . "'>");
		echo("<p>BIN: " . $bin_1 . "</p><input type='hidden' name='bin_1' value='" . $bin_1 . "'>");
		echo("<p>Coordinates: <span id='coords_1'>" . $lat_1 . ", " . $long_1 . "</span> <a id='map_1_reset' class='reset' onclick='map_1_reset()' href='#'>(reset)</a></p>");
		if (empty($_GET['lat_1'])) {
			echo("<div id='map_1' class='map'></div>");
		}
		echo("<input id='lat_1' type='hidden' name='lat_1' value='" . $lat_1 . "'></div>");
		echo("<input id='long_1' type='hidden' name='long_1' value='" . $long_1 . "'></div>");

		// Endpoint 2 (get vars from GET if we already have it)
                if (isset($_GET['address_2'])) {
                        $address_2 = $_GET['address_2'];
                        $bin_2 = $_GET['bin_2'];
                        $lat_2 = $_GET['lat_2'];
                        $long_2 = $_GET['long_2'];
                } else {
			if (empty($_GET['input_address_2'])) {
				// Get variables from spreadsheet
				echo ("<p>For endpoint #2, you entered install number: " . $_GET['input_install_2'] . "</p>");
				$install_get = file_get_contents("http://db.grandsvc.mesh/api/v1/installs/" . $_GET['input_install_2']  . "/", false, $cred_context);
				$install_json = json_decode($install_get);
				$building_get = file_get_contents("http://db.grandsvc.mesh/api/v1/buildings/" . $install_json->building  . "/", false, $cred_context);
				$building_json = json_decode($building_get);
				$address_2 = $building_json->street_address . ", " . $building_json->city . ", " . $building_json->state . ", " . $building_json->zip_code;
				$bin_2 = $building_json->bin;
				$lat_2 = $building_json->latitude;
				$long_2 = $building_json->longitude;
			} else {
				// Get variables from DCP dataset
				echo ("<p>For endpoint #2, you entered address: " . $_GET['input_address_2'] . "</p>");
                        	$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($_GET['input_address_2']));
                        	$address_2 = json_decode($dcp_get, true)['features']['0']['properties']['label'];
                        	$bin_2 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
                        	$lat_2 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["1"];
                        	$long_2 = json_decode($dcp_get, true)['features']['0']['geometry']['coordinates']["0"];
			}
		}
                echo("<p>Matched Address: " . $address_2 . "</p><input type='hidden' name='address_2' value='" . $address_2 . "'>");
                echo("<p>BIN: " . $bin_2 . "</p><input type='hidden' name='bin_2' value='" . $bin_2 . "'>");
                echo("<p>Coordinates: <span id='coords_2'>" . $lat_2 . ", " . $long_2 . "</span> <a id='map_2_reset' class='reset' onclick='map_2_reset()' href='#'>(reset)</a></p>");
                if (empty($_GET['lat_2'])) {
                        echo("<div id='map_2' class='map'></div>");
		}
                echo("<input id='lat_2' type='hidden' name='lat_2' value='" . $lat_2 . "'></div>");
                echo("<input id='long_2' type='hidden' name='long_2' value='" . $long_2 . "'></div>");
	}
}

?>

<p class='height_fields'>For endpoint #1, where do you want to get the height from?
<input type='radio' id='dob1' name='height_radio_1' value='dob' <?php if(isset($_GET['height_radio_1'])){if($_GET['height_radio_1']=='dob'){echo('checked');}} ?>>
<label for='dob1'>DOB</label>
<input type='radio' id='spreadsheet1' name='height_radio_1' value='spreadsheet' <?php if(isset($_GET['height_radio_1'])){if($_GET['height_radio_1']=='spreadsheet'){echo('checked');}} ?>>
<label for='spreadsheet'>DB</label>
<input type='number' name='height_manual_1' placeholder='Manual Entry (in meters)' value="<?php if(isset($_GET['height_manual_1'])) {echo($_GET['height_manual_1']);} ?>">
</p>

<p class='height_fields'>For endpoint #2, where do you want to get the height from?
<input type='radio' id='dob2' name='height_radio_2' value='dob' <?php if(isset($_GET['height_radio_2'])){if($_GET['height_radio_2']=='dob'){echo('checked');}} ?>>
<label for='dob2'>DOB</label>
<input type='radio' id='spreadsheet2' name='height_radio_2' value='spreadsheet' <?php if(isset($_GET['height_radio_2'])){if($_GET['height_radio_2']=='spreadsheet'){echo('checked');}} ?>>
<label for='spreadsheet2'>DB</label>
<input type='number' name='height_manual_2' placeholder='Manual Entry (in meters)' value="<?php if(isset($_GET['height_manual_2'])) {echo($_GET['height_manual_2']);} ?>">
</p>
<p class="height_fields"><button type="submit">Next</button></p>

<?php

// Input validation
if (isset($_GET['input_address_1'])) {
	// Throw an error if there are no entries for either of the endpoints.
	$heights_fail = False;
	if (empty($_GET['height_radio_1']) && empty($_GET['height_manual_1']) || empty($_GET['height_radio_2']) && empty($_GET['height_manual_2'])) {
		echo("Not sure how you expect me to do anything without knowing from where I should get the height. Pick something!");
		$heights_fail = True;
	}

	// Turn off the input fields and display what the user entered.
	if (!$heights_fail) {
		echo ("<style>.height_fields {display:none} .option_fields {display:block} .reset {display:none}</style>");
		// Endpoint 1
		echo("<p>Height of endpoint #1: ");
		if (empty($_GET['height_manual_1'])) {
			// Get height using BIN from DOB dataset
			if ($_GET['height_radio_1'] == 'dob') {
				$dob_get = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $bin_1);
				$meters_1 = json_decode($dob_get, true)['0']['heightroof'] * 0.3048;
				$alt_mode_1 = "relativeToGround";
			}
			// Get height using cell in db
			if ($_GET['height_radio_1'] == 'spreadsheet') {
				// Use the Install # to query the db directly
				$alt_mode_1 = "absolute";
				if (empty($_GET['input_address_1'])) {
					$install_get = file_get_contents("http://db.grandsvc.mesh/api/v1/installs/" . $_GET['input_install_1']  . "/", false, $cred_context);
					$install_json = json_decode($install_get);
					$building_get = file_get_contents("http://db.grandsvc.mesh/api/v1/buildings/" . $install_json->building  . "/", false, $cred_context);
					$building_json = json_decode($building_get);
					$meters_1 = $building_json->altitude;
				} else {
					// Get BIN from DCP dataset
					$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($_GET['input_address_1']));
					$bin_1 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
					$url = "http://db.grandsvc.mesh/api/v1/buildings/lookup/?bin=" . $bin_1;
					$building_get = file_get_contents($url, false, $cred_context);
					$building_json = json_decode($building_get);
					$meters_1 = $building_json->results[0]->altitude;
				}
			}
		} else {
			// Use entered value for height
			$meters_1 = $_GET['height_manual_1'];
			$alt_mode_1 = "relativeToGround";
		}
		if (!$heights_fail) {
			echo($meters_1 . "m | " . $meters_1*3.28084 . "ft</p>");
            echo("<input type='hidden' name='height_1' value='" . $meters_1 . "'>");
            echo("<input type='hidden' name='alt_mode' value='" . $alt_mode_1 . "'>");
		}

		// Endpoint 2
		echo("<p>Height of endpoint #2: ");
			if (empty($_GET['height_manual_2'])) {
				// Get height using BIN from DOB dataset
				if ($_GET['height_radio_2'] == 'dob') {
					$dob_get = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $bin_2);
					$meters_2 = json_decode($dob_get, true)['0']['heightroof'] * 0.3048;
					$alt_mode_2 = "relativeToGround";
				}
				// Get height using cell in db
				if ($_GET['height_radio_2'] == 'spreadsheet') {
					// Use the Install # to query the db directly
					$alt_mode_2 = "absolute";
					if (empty($_GET['input_address_2'])) {
						$install_get = file_get_contents("http://db.grandsvc.mesh/api/v1/installs/" . $_GET['input_install_2']  . "/", false, $cred_context);
						$install_json = json_decode($install_get);
						$building_get = file_get_contents("http://db.grandsvc.mesh/api/v1/buildings/" . $install_json->building  . "/", false, $cred_context);
						$building_json = json_decode($building_get);
						$meters_2 = $building_json->altitude;
					} else {
						// Get BIN from DCP dataset
						$dcp_get = file_get_contents('https://geosearch.planninglabs.nyc/v2/search?text=' . urlencode($_GET['input_address_2']));
						$bin_2 = json_decode($dcp_get, true)['features']['0']['properties']['addendum']['pad']['bin'];
						$url = "http://db.grandsvc.mesh/api/v1/buildings/lookup/?bin=" . $bin_2;
						$building_get = file_get_contents($url, false, $cred_context);
						$building_json = json_decode($building_get);
						$meters_2 = $building_json->results[0]->altitude;
					}
				}
			} else {
				// Use entered value for height
				$meters_2 = $_GET['height_manual_2'];
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
<p class="option_fields">Line Color: <input type="text" id="color" name="color" value="ff0000ff"></p>
<p class="option_fields"><input type="checkbox" id="fresnel" name="fresnel"><label for="fresnel">Fresnel</label></p>
<input class="option_fields" type="submit" value="Download KML" name="kml" />

</form>

<button onclick="goBack();">Go Back</button><button onclick="window.location.href=window.location.origin + window.location.pathname">Start Over</button>

<script src="./color-picker.js"></script>
<script>
const picker = new CP(document.querySelector('#color'));
picker.on('change', function (r, g, b, a) {
	var red = r.toString(16);
	var green = g.toString(16);
	var blue = b.toString(16);
	var alpha = Math.trunc(a*255).toString(16);

	if (red.length == 1) {red = 0 + red}
	if (green.length == 1) {green = 0 + green}
	if (blue.length == 1) {blue = 0 + blue}
	if (alpha.length == 1) {alpha = 0 + alpha}

	this.source.value = alpha + blue + green + red;
});

function getKML(name1, name2, col, lat1, long1, height1, lat2, long2, height2) {
	fetch('./kml.php?' + new URLSearchParams({
		name_1: name1,
		name_2: name2,
		color: col,
		lat_1: lat1,
		long_1: long1,
		height_1: height1,
		lat_2: lat2,
		long_2: long2,
		height_2: height2,
	}))
	.then( res => res.blob() )
	.then( blob => {
		var file = window.URL.createObjectURL(blob);
		window.location.assign(file);
	});
}

const lat_1_def = "<?php echo $lat_1 ?>";
const long_1_def = "<?php echo $long_1 ?>";
const lat_2_def = "<?php echo $lat_2 ?>";
const long_2_def = "<?php echo $long_2 ?>";

mbAttr = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
mbUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

base_layer_1 = L.tileLayer(mbUrl, {
        id: 'mapbox.streets',
        attribution: mbAttr
});
base_layer_2 = L.tileLayer(mbUrl, {
        id: 'mapbox.streets',
        attribution: mbAttr
});

var map_1 = L.map('map_1', {
        center: [lat_1_def,long_1_def],
        zoom: 20,
        layers: [base_layer_1]
});
var map_2 = L.map('map_2', {
        center: [lat_2_def,long_2_def],
        zoom: 20,
        layers: [base_layer_2]
});

var marker_1 = new L.marker([lat_1_def,long_1_def],{
        draggable: true,
        autoPan: true
}).addTo(map_1);
var marker_2 = new L.marker([lat_2_def,long_2_def],{
        draggable: true,
        autoPan: true
}).addTo(map_2);

marker_1.on('dragend', function(e) {
        document.getElementById("coords_1").innerHTML = marker_1.getLatLng().lat + ", " + marker_1.getLatLng().lng;
        document.getElementById("lat_1").value = marker_1.getLatLng().lat;
        document.getElementById("long_1").value = marker_1.getLatLng().lng;
});
marker_2.on('dragend', function(e) {
        document.getElementById("coords_2").innerHTML = marker_2.getLatLng().lat + ", " + marker_2.getLatLng().lng;
        document.getElementById("lat_2").value = marker_2.getLatLng().lat;
        document.getElementById("long_2").value = marker_2.getLatLng().lng;
});

function map_1_reset() {
        marker_1.setLatLng([lat_1_def,long_1_def]);
        document.getElementById("coords_1").innerHTML = lat_1_def + ", " + long_1_def;
        document.getElementById("lat_1").value = lat_1_def;
        document.getElementById("long_1").value = long_1_def;
}
function map_2_reset() {
        marker_2.setLatLng([lat_2_def,long_2_def]);
        document.getElementById("coords_2").innerHTML = lat_2_def + ", " + long_2_def;
        document.getElementById("lat_2").value = lat_2_def;
        document.getElementById("long_2").value = long_2_def;
}

</script>

</body>

</html>
