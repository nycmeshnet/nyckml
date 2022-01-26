<?php

/*

Typed Address to Real Address, BINs, and Coords
https://geosearch.planninglabs.nyc/docs/

BIN to Height
https://dev.socrata.com/foundry/data.cityofnewyork.us/7w4b-tj9d

*/

if (isset($_GET['kml'])) {
        $json1 = file_get_contents('https://geosearch.planninglabs.nyc/v1/search?text=' . urlencode($_GET['first']));
        $array1 = json_decode($json1, true);

        $json2 = file_get_contents('https://geosearch.planninglabs.nyc/v1/search?text=' . urlencode($_GET['second']));
        $array2 = json_decode($json2, true);

        $json3 = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $array1['features']['0']['properties']['pad_bin']);
        $array3 = json_decode($json3, true);
        $meters1 = $array3['0']['heightroof'] * 0.3048;

        $json4 = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $array2['features']['0']['properties']['pad_bin']);
        $array4 = json_decode($json4, true);
        $meters2 = $array4['0']['heightroof'] * 0.3048;


            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Content-Type: text/xml");
            //header("Content-Length:".filesize($attachment_location));
            header("Content-Disposition: attachment; filename=" . $array1['features']['0']['properties']['pad_bin'] . "-" . $array2['features']['0']['properties']['pad_bin'] . ".kml");
            echo('<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Document>
	<name>' . $array1['features']['0']['properties']['pad_bin'] . "-" . $array2['features']['0']['properties']['pad_bin'] . '.kml</name>
        <Style id="inline">
                <LineStyle>
                        <color>ff0000ff</color>
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
                        <color>ff0000ff</color>
                        <width>2</width>
                </LineStyle>
        </Style>
        <Placemark>
                <name>' . $array1['features']['0']['properties']['pad_bin'] . "-" . $array2['features']['0']['properties']['pad_bin'] . '</name>
                <styleUrl>#inline0</styleUrl>
                <LineString>
                        <extrude>1</extrude>
                        <tessellate>1</tessellate>
                        <altitudeMode>relativeToGround</altitudeMode>
                        <coordinates>
      ' . $array1['features']['0']['geometry']['coordinates']["0"] . ',' . $array1['features']['0']['geometry']['coordinates']["1"] . ',' . $meters1 . ' ' . $array2['features']['0']['geometry']['coordinates']["0"] . ',' . $array2['features']['0']['geometry']['coordinates']["1"] . ',' . $meters2 . '
                        </coordinates>
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
</head>

<body>

<h1>NYC Building KML Tool</h1>

<form method="get">

<?php

echo ("<p>Enter the first address: ");
if (isset($_GET['first'])) {
	echo ($_GET['first'] . "</p>");
	$json1 = file_get_contents('https://geosearch.planninglabs.nyc/v1/search?text=' . urlencode($_GET['first']));
	$array1 = json_decode($json1, true);
	echo("<p>Matched Address: " . $array1['features']['0']['properties']['label'] . "</p>");
        echo("<p>BIN: " . $array1['features']['0']['properties']['pad_bin'] . "</p>");
	echo("<p>Coordinates: " . $array1['features']['0']['geometry']['coordinates']["1"] . ", " . $array1['features']['0']['geometry']['coordinates']["0"] . "</p>");
	echo("<input type='hidden' name='first' value='" . $_GET['first'] . "'>");

        echo ("<p>Enter the second address: ");
	if (isset($_GET['second'])) {
        	echo ($_GET['second'] . "</p>");
       		$json2 = file_get_contents('https://geosearch.planninglabs.nyc/v1/search?text=' . urlencode($_GET['second']));
        	$array2 = json_decode($json2, true);
        	echo("<p>Matched Address: " . $array2['features']['0']['properties']['label'] . "</p>");
	        echo("<p>BIN: " . $array2['features']['0']['properties']['pad_bin'] . "</p>");
	        echo("<p>Coordinates: " . $array2['features']['0']['geometry']['coordinates']["1"] . ", " . $array2['features']['0']['geometry']['coordinates']["0"] . "</p>");
	        echo("<input type='hidden' name='second' value='" . $_GET['second'] . "'>");

		if (isset($_GET['heights'])) {
			echo("<p>Height of first address: ");
			$json3 = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $array1['features']['0']['properties']['pad_bin']);
			$array3 = json_decode($json3, true);
			$meters1 = $array3['0']['heightroof'] * 0.3048;
			echo($array3['0']['heightroof'] . "ft | " . $meters1 . "m</p>");

                        echo("<p>Height of second address: ");
                        $json4 = file_get_contents('https://data.cityofnewyork.us/resource/7w4b-tj9d.json?bin=' . $array2['features']['0']['properties']['pad_bin']);
                        $array4 = json_decode($json4, true);
                        $meters2 = $array4['0']['heightroof'] * 0.3048;
                        echo($array4['0']['heightroof'] . "ft | " . $meters2 . "m</p>");
	                echo("<input type='hidden' name='heights'>");

			echo("<p>I made a KML for you. <button type='submit' name='kml'>Click here to download it!</button></p>");

		} else {
			echo("<p>Do these addresses look right? <button type='submit' name='heights'>Yes, give me my heights!</button><button onclick=\"window.location.href='/programs/los/'>\">No, let me start over.</button>");
		}

	} else {
        	echo ("<input name='second'></input><button>Next</button></p>");
	}

} else {
	echo ("<input name='first'></input><button>Next</button></p>");
}

?>

</form>

<button onclick="history.back()">Go Back</button><button onclick="window.location.href='/programs/nyckml'">Start Over</button>

</body>

</html>
