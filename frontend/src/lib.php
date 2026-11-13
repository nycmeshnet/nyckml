<?php
/*

Lookups against the NYC and NYC Mesh data sources, plus KML generation.

Typed Address to Real Address, BINs, and Coords
https://geosearch.planninglabs.nyc/docs/

BIN to Roof Height and Ground Elevation (both in feet)
https://data.cityofnewyork.us/City-Government/Building-Footprints/5zhs-2jue/about_data

Install Number to Building, and Building Altitude (meters above sea level)
https://db.nycmesh.net/api/v1/

Ground Elevation for spots NYC has no footprint for
https://epqs.nationalmap.gov/v1/docs

All heights are worked out as meters above sea level, which is what MeshDB stores (it computes them as
height_roof + ground_elevation from the footprints dataset). Having one datum means both ends of the line
can share KML's "absolute" altitude mode no matter where each end's height came from.

*/

const GEOSEARCH_API = 'https://geosearch.planninglabs.nyc/v2/search';
const FOOTPRINTS_API = 'https://data.cityofnewyork.us/resource/5zhs-2jue.json';
const MESHDB_API = 'https://db.nycmesh.net/api/v1';
const ELEVATION_API = 'https://epqs.nationalmap.gov/v1/json';
const FRESNEL_URL = 'https://www.radiofresnel.com/';

const METERS_PER_FOOT = 0.3048;
const EARTH_RADIUS_M = 6371008.8;

// A lookup that failed in a way worth telling the user about. Messages are shown as-is, so phrase them for people.
class LookupError extends Exception {}

function h($text): string {
	return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function http_request(string $url, array $options = []): string {
	$host = parse_url($url, PHP_URL_HOST);
	$ch = curl_init($url);
	curl_setopt_array($ch, $options + [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,  // NYC Open Data sometimes takes 10+ seconds to answer
		CURLOPT_USERAGENT => 'nyckml',
	]);
	// NYC Open Data in particular has hiccups (503s, stalls), so give connection failures and 5xx a couple more tries.
	for ($attempt = 1; $attempt <= 3; $attempt++) {
		$body = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if ($body !== false && $status < 500) {
			break;
		}
		usleep(500000);
	}
	if ($body === false) {
		throw new LookupError("couldn't reach $host (" . curl_error($ch) . ").");
	}
	if ($status === 404) {
		throw new LookupError("$host doesn't have that.", 404);
	}
	if ($status !== 200) {
		throw new LookupError("$host answered with HTTP $status.");
	}
	return $body;
}

function http_get_json(string $url, array $headers = []): array {
	$data = json_decode(http_request($url, [CURLOPT_HTTPHEADER => $headers]), true);
	if (!is_array($data)) {
		throw new LookupError(parse_url($url, PHP_URL_HOST) . " sent back something that isn't JSON.");
	}
	return $data;
}

// Addresses with no real building get a "million BIN" placeholder like 1000000 (borough digit + zeros).
function is_real_bin(string $bin): bool {
	return ctype_digit($bin) && strlen($bin) === 7 && substr($bin, 1) !== '000000';
}

// ---- Endpoints -------------------------------------------------------------------------------------------------

const ADDRESS_ABBREVIATIONS = [
	'st' => 'street', 'ave' => 'avenue', 'av' => 'avenue', 'pl' => 'place', 'rd' => 'road', 'blvd' => 'boulevard',
	'ct' => 'court', 'dr' => 'drive', 'ln' => 'lane', 'pkwy' => 'parkway', 'ter' => 'terrace', 'hwy' => 'highway',
	'sq' => 'square', 'n' => 'north', 's' => 'south', 'e' => 'east', 'w' => 'west',
];

// "20 W 34th St." -> "20 west 34 street", so typed addresses can be compared with GeoSearch labels.
function normalize_address(string $text): string {
	$words = preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY);
	$words = array_map(fn($word) => ADDRESS_ABBREVIATIONS[$word] ?? preg_replace('/^(\d+)(st|nd|rd|th)$/', '$1', $word), $words);
	return implode(' ', $words);
}

// Typed address -> ['address', 'bin', 'lat', 'long', 'alternatives'] via NYC Planning's GeoSearch.
// GeoSearch gives most partial matches the same score, so "53 bridge st" can come back as "53 BR COURT" first.
// This prefers a result whose house number and street are what was typed, and keeps a few other results
// as 'alternatives' so the user can pick a different one.
function lookup_address(string $text): array {
	$data = http_get_json(GEOSEARCH_API . '?' . http_build_query(['text' => $text, 'size' => 10]));
	$candidates = array_map(fn($feature) => [
		'address' => $feature['properties']['label'],
		'bin' => (string) ($feature['properties']['addendum']['pad']['bin'] ?? ''),
		'lat' => (float) $feature['geometry']['coordinates'][1],
		'long' => (float) $feature['geometry']['coordinates'][0],
		'borough' => $feature['properties']['borough'] ?? '',
		'zip' => $feature['properties']['postalcode'] ?? '',
	], $data['features'] ?? []);
	if (!$candidates) {
		throw new LookupError("couldn't find an NYC address matching \"$text\".");
	}

	$typed = normalize_address($text) . ' ';
	$best = $candidates[0];
	foreach ($candidates as $candidate) {
		if (str_starts_with($typed, normalize_address(strtok($candidate['address'], ',')) . ' ')) {
			$best = $candidate;
			break;
		}
	}

	$seen = [$best['address'] . $best['bin'] => true];
	$best['alternatives'] = [];
	foreach ($candidates as $candidate) {
		if (count($best['alternatives']) < 5 && !isset($seen[$candidate['address'] . $candidate['bin']])) {
			$seen[$candidate['address'] . $candidate['bin']] = true;
			$best['alternatives'][] = $candidate;
		}
	}
	return $best;
}

// NYC Mesh install number -> ['address', 'bin', 'lat', 'long'] via MeshDB.
function lookup_install(string $install): array {
	$building = meshdb_building_for_install($install);
	return [
		'address' => implode(', ', array_filter([
			$building['street_address'] ?? '', $building['city'] ?? '', $building['state'] ?? '', $building['zip_code'] ?? '',
		])),
		'bin' => (string) ($building['bin'] ?? ''),
		'lat' => (float) $building['latitude'],
		'long' => (float) $building['longitude'],
	];
}

function meshdb_get(string $path): array {
	$key = getenv('MESHDB_KEY');
	if (!$key) {
		throw new LookupError('MeshDB lookups aren\'t set up on this server (MESHDB_KEY is missing).');
	}
	return http_get_json(MESHDB_API . $path, ['Authorization: token ' . $key]);
}

function meshdb_building_for_install(string $install): array {
	try {
		$install_json = meshdb_get('/installs/' . rawurlencode($install) . '/');
	} catch (LookupError $e) {
		throw $e->getCode() === 404 ? new LookupError("MeshDB has no install #$install.") : $e;
	}
	$building_id = $install_json['building']['id'] ?? null;
	if (!$building_id) {
		throw new LookupError("install #$install isn't attached to a building in MeshDB.");
	}
	return meshdb_get('/buildings/' . rawurlencode($building_id) . '/');
}

function meshdb_building_for_bin(string $bin): array {
	$data = meshdb_get('/buildings/lookup/?' . http_build_query(['bin' => $bin]));
	$building = $data['results'][0] ?? null;
	if (!$building) {
		throw new LookupError("MeshDB has no building with BIN $bin.");
	}
	return $building;
}

// ---- Heights ---------------------------------------------------------------------------------------------------

// BIN -> ['roof' => m above ground, 'ground' => m above sea level], or null if NYC has no footprint for it.
function lookup_footprint(string $bin): ?array {
	if (!is_real_bin($bin)) {
		return null;
	}
	$rows = http_get_json(FOOTPRINTS_API . '?' . http_build_query([
		'bin' => $bin,
		'$select' => 'height_roof,ground_elevation',
		'$limit' => 1,
	]));
	$row = $rows[0] ?? null;
	if (!$row || !is_numeric($row['ground_elevation'] ?? null)) {
		return null;
	}
	return [
		'roof' => is_numeric($row['height_roof'] ?? null) ? $row['height_roof'] * METERS_PER_FOOT : null,
		'ground' => $row['ground_elevation'] * METERS_PER_FOOT,
	];
}

// Ground elevation in meters above sea level at any point, from USGS.
function lookup_ground_elevation(float $lat, float $long): float {
	$data = http_get_json(ELEVATION_API . '?' . http_build_query([
		'x' => $long, 'y' => $lat, 'wkid' => 4326, 'units' => 'Meters', 'includeDate' => 'false',
	]));
	$value = $data['value'] ?? null;
	if (!is_numeric($value) || $value < -1000) {  // EPQS answers -1000000 where it has no data
		throw new LookupError("couldn't find the ground elevation at this spot.");
	}
	return (float) $value;
}

// Works out an endpoint's ['ground', 'altitude'] (meters above sea level) and where the height came from.
// $source is 'dob' or 'db'; a non-empty $manual (meters above ground) wins over it.
function resolve_height(array $endpoint, string $source, string $manual): array {
	$footprint = lookup_footprint($endpoint['bin']);
	$ground = $footprint ? $footprint['ground'] : lookup_ground_elevation($endpoint['lat'], $endpoint['long']);

	if ($manual !== '') {
		if (!is_numeric($manual) || $manual < 0 || $manual > 1000) {
			throw new LookupError("\"$manual\" isn't a height I can use. Enter meters above the ground.");
		}
		return ['ground' => $ground, 'altitude' => $ground + $manual, 'height_source' => 'entered manually'];
	}
	if ($source === 'dob') {
		if (!$footprint || $footprint['roof'] === null) {
			throw new LookupError("NYC's building footprints have no roof height for BIN {$endpoint['bin']}. Try DB or enter one.");
		}
		return ['ground' => $ground, 'altitude' => $ground + $footprint['roof'], 'height_source' => 'DOB'];
	}
	if ($source === 'db') {
		if ($endpoint['install'] !== '') {
			$building = meshdb_building_for_install($endpoint['install']);
		} elseif (is_real_bin($endpoint['bin'])) {
			$building = meshdb_building_for_bin($endpoint['bin']);
		} else {
			throw new LookupError("this address has no BIN to look up in MeshDB. Try DOB or enter a height.");
		}
		if (!is_numeric($building['altitude'] ?? null)) {
			throw new LookupError('MeshDB has no altitude for this building. Try DOB or enter a height.');
		}
		return ['ground' => $ground, 'altitude' => (float) $building['altitude'], 'height_source' => 'DB'];
	}
	throw new LookupError('pick DOB or DB, or enter a height.');
}

// ---- Output ----------------------------------------------------------------------------------------------------

// '#rrggbb' -> KML's 'aabbggrr'.
function kml_color(string $hex, string $alpha = 'ff'): string {
	[$red, $green, $blue] = str_split(substr($hex, 1, 6), 2);
	return strtolower($alpha . $blue . $green . $red);
}

function build_kml(array $a, array $b, string $color, string $name): string {
	$x = fn($text) => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	$coordinates = fn($e) => sprintf('%.7F,%.7F,%.2F', $e['long'], $e['lat'], $e['altitude']);
	$line_color = kml_color($color);
	return <<<KML
	<?xml version="1.0" encoding="UTF-8"?>
	<kml xmlns="http://www.opengis.net/kml/2.2">
	<Document>
		<name>{$x($name)}.kml</name>
		<Style id="line">
			<LineStyle>
				<color>{$line_color}</color>
				<width>2</width>
			</LineStyle>
		</Style>
		<Placemark>
			<name>{$x($name)}</name>
			<styleUrl>#line</styleUrl>
			<LineString>
				<extrude>1</extrude>
				<tessellate>1</tessellate>
				<altitudeMode>absolute</altitudeMode>
				<coordinates>{$coordinates($a)} {$coordinates($b)}</coordinates>
			</LineString>
		</Placemark>
	</Document>
	</kml>

	KML;
}

// Asks radiofresnel.com for a KML of the Fresnel zone between the two endpoints. It takes the same fields as the
// form on its homepage: unsigned coordinates with a +1/-1 hemisphere "coef", whole-meter altitudes above sea level,
// and an aabbggrr colour (its presets use 7f alpha).
function fresnel_kml(array $a, array $b, float $frequency_mhz, float $zone, string $color): string {
	$fields = [];
	foreach ([1 => $a, 2 => $b] as $n => $endpoint) {
		$fields += [
			"name$n" => $endpoint['name'],
			"lon$n" => abs($endpoint['long']),
			"lon{$n}_coef" => $endpoint['long'] < 0 ? -1 : 1,
			"lat$n" => abs($endpoint['lat']),
			"lat{$n}_coef" => $endpoint['lat'] < 0 ? -1 : 1,
			"altitude$n" => round($endpoint['altitude']),
			"alt{$n}_coef" => 1,
		];
	}
	$fields += [
		'frequency' => $frequency_mhz,
		'frequency_coefficient' => 1,
		'zone' => $zone,
		'colour' => strtoupper(kml_color($color, '7f')),
		'my-form' => 'Get kml',
	];
	$kml = http_request(FRESNEL_URL, [
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query($fields),
		CURLOPT_TIMEOUT => 60,
	]);
	if (strpos($kml, '<kml') === false) {
		throw new LookupError("radiofresnel.com didn't send back a KML. Their site may have changed.");
	}
	return $kml;
}

// Distance, compass bearings both ways, and the elevation angle from #1 to #2, ignoring earth curvature.
function link_stats(array $a, array $b): array {
	$bearing = function (array $from, array $to): float {
		[$lat1, $lat2, $dlong] = [deg2rad($from['lat']), deg2rad($to['lat']), deg2rad($to['long'] - $from['long'])];
		$y = sin($dlong) * cos($lat2);
		$x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dlong);
		return fmod(rad2deg(atan2($y, $x)) + 360, 360);
	};
	$dlat = deg2rad($b['lat'] - $a['lat']);
	$dlong = deg2rad($b['long'] - $a['long']);
	$hav = sin($dlat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dlong / 2) ** 2;
	$distance = 2 * EARTH_RADIUS_M * asin(sqrt($hav));
	return [
		'distance' => $distance,
		'azimuth_1' => $bearing($a, $b),
		'azimuth_2' => $bearing($b, $a),
		'elevation' => rad2deg(atan2($b['altitude'] - $a['altitude'], $distance)),
	];
}
