<?php
/*

The whole tool is one GET form that walks through three steps. Each "Next" re-submits everything gathered so
far (carried along as hidden fields), so every step is a plain URL that can be bookmarked or shared.

  1. Endpoints  An address (NYC GeoSearch) or an NYC Mesh install number (MeshDB) for each end of the link.
  2. Heights    Where each end's height comes from: DOB building footprints, MeshDB, or typed in.
  3. Export     Download the KML, download a Fresnel zone KML from radiofresnel.com, or preview it in 3D.

The entry points (public/index.php and public/embed/index.php) set $embed before including this file.

*/

require __DIR__ . '/lib.php';

const DEFAULT_COLOR = '#ff0000';
const DEFAULT_FREQUENCY_MHZ = 5710;
const DEFAULT_ZONE = 2.0;

$embed_parent_origin = getenv('EMBED_PARENT_ORIGIN') ?: 'https://dantonio.tech';
if (!preg_match('#^https?://[a-z0-9.-]+(:[0-9]+)?$#i', $embed_parent_origin)) {
	$embed_parent_origin = 'https://dantonio.tech';
}
$assets = $embed ? '../' : '';
if ($embed) {
	header("Content-Security-Policy: frame-ancestors 'self' $embed_parent_origin");
}

// A query string value as a trimmed string ('' when missing).
function param(string $name): string {
	$value = $_GET[$name] ?? '';
	return is_string($value) ? trim($value) : '';
}

function number_param(string $name, float $min, float $max, float $default): float {
	$value = param($name);
	return is_numeric($value) && $value >= $min && $value <= $max ? (float) $value : $default;
}

// Endpoint #$n as ['address', 'bin', 'lat', 'long', 'install', 'name'], looked up on step 1 and carried along after.
function resolve_endpoint(int $n): array {
	$install = ltrim(param("input_install_$n"), '#');
	if (param("address_$n") !== '') {
		// Already looked up on an earlier step. The coordinates may have been moved on the map since.
		if (!is_numeric(param("lat_$n")) || !is_numeric(param("long_$n"))) {
			throw new LookupError('the coordinates got mangled somewhere. Start over?');
		}
		$endpoint = [
			'address' => param("address_$n"),
			'bin' => ctype_digit(param("bin_$n")) ? param("bin_$n") : '',
			'lat' => (float) param("lat_$n"),
			'long' => (float) param("long_$n"),
		];
	} elseif ($install !== '') {
		$endpoint = lookup_install($install);
	} else {
		$endpoint = lookup_address(param("input_address_$n"));
	}
	$endpoint['install'] = $install;
	$endpoint['name'] = $endpoint['bin'] ?: ($install !== '' ? "install-$install" : "endpoint-$n");
	return $endpoint;
}

// Step 2 again, with a different GeoSearch result filled in for endpoint #$n.
function pick_match_url(int $n, array $match): string {
	return '?' . http_build_query([
		"input_address_$n" => $match['address'],
		"address_$n" => $match['address'],
		"bin_$n" => $match['bin'],
		"lat_$n" => $match['lat'],
		"long_$n" => $match['long'],
	] + $_GET);
}

function format_height(float $meters): string {
	return number_format($meters, 1) . ' m | ' . number_format($meters / METERS_PER_FOOT, 1) . ' ft';
}

function send_kml(string $filename, string $kml): void {
	header('Content-Type: application/vnd.google-earth.kml+xml');
	header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename) . '"');
	echo $kml;
}

$errors = [];
$step = 1;
$endpoints = [];

// The step being asked for. The Next buttons send step=2 and step=3, and Go Back links to the step before.
// Links from before there was a step param are worked out from which fields they have.
$requested = (int) param('step');
if (!$requested) {
	$requested = isset($_GET['height_manual_1']) ? 3 : (isset($_GET['input_address_1']) ? 2 : 1);
}

// Step 1 submitted: look up both endpoints.
if ($requested >= 2) {
	foreach ([1, 2] as $n) {
		$address = param("input_address_$n");
		$install = ltrim(param("input_install_$n"), '#');
		if ($address === '' && $install === '') {
			$errors = ['Not sure how you expect me to do anything without either an address or install number for each endpoint. Enter something!'];
			break;
		}
		if ($address !== '' && $install !== '') {
			$errors = ['Not sure how you expect me to do anything with both an address and an install number for an endpoint. Pick one!'];
			break;
		}
		if ($install !== '' && !ctype_digit($install)) {
			$errors[] = "Endpoint #$n: install numbers are just digits.";
		}
	}
	if (!$errors) {
		foreach ([1, 2] as $n) {
			try {
				$endpoints[$n] = resolve_endpoint($n);
			} catch (LookupError $e) {
				$errors[] = "Endpoint #$n: " . $e->getMessage();
			}
		}
	}
	if (!$errors) {
		$step = 2;
	}
}

// Step 2 submitted: work out both heights.
if ($step === 2 && $requested >= 3) {
	foreach ([1, 2] as $n) {
		if (param("height_radio_$n") === '' && param("height_manual_$n") === '') {
			$errors = ['Not sure how you expect me to do anything without knowing from where I should get the height. Pick something!'];
			break;
		}
	}
	if (!$errors) {
		foreach ([1, 2] as $n) {
			try {
				$endpoints[$n] += resolve_height($endpoints[$n], param("height_radio_$n"), param("height_manual_$n"));
			} catch (LookupError $e) {
				$errors[] = "Endpoint #$n: " . $e->getMessage();
			}
		}
	}
	if (!$errors) {
		$step = 3;
	}
}

// Step 3: export options.
$color = preg_match('/^#[0-9a-f]{6}$/i', param('color')) ? strtolower(param('color')) : DEFAULT_COLOR;
$frequency = number_param('frequency', 1, 100000, DEFAULT_FREQUENCY_MHZ);
$zone = number_param('zone', 0.5, 6, DEFAULT_ZONE);
if ($step === 3) {
	$name = $endpoints[1]['name'] . '-' . $endpoints[2]['name'];
	if (isset($_GET['download'])) {
		send_kml("$name.kml", build_kml($endpoints[1], $endpoints[2], $color, $name));
		exit;
	}
	if (isset($_GET['fresnel'])) {
		try {
			send_kml("$name-fresnel.kml", fresnel_kml($endpoints[1], $endpoints[2], $frequency, $zone, $color));
			exit;
		} catch (LookupError $e) {
			$errors[] = 'Fresnel: ' . $e->getMessage();
		}
	}
	$link = link_stats($endpoints[1], $endpoints[2]);
}

// Go Back reloads the previous step from this URL, so everything entered so far stays filled in.
$back_url = $step > 1
	? '?' . http_build_query(['step' => $step - 1] + array_diff_key($_GET, ['download' => 1, 'fresnel' => 1]))
	: '';

// What nyckml.js needs to draw the maps and the 3D preview.
$page_data = [
	'step' => $step,
	'endpoints' => $step > 1 ? $endpoints : null,
	'cesiumIonToken' => (string) getenv('CESIUM_ION_TOKEN'),
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NYC Building KML Tool</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
	integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
	integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@v0.25.1/dist/coloris.min.css"
	integrity="sha384-DY3umZptOgjUNshBFbvu1+3RVFPoD1/CgGcc1yyJ77/aFOJ7jtN4BORnz/D/xF0n" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/gh/mdbassit/Coloris@v0.25.1/dist/coloris.min.js"
	integrity="sha384-m3EbrZZVQTNZ1LPMLgsy+hURA3oTawLSZ+nNXeYn7HYTYSKCd+e/hO3ecY21wXFf" crossorigin="anonymous"></script>
<link rel="stylesheet" href="<?= $assets ?>nyckml.css">
<script id="nyckml-data" type="application/json"><?= json_encode($page_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="<?= $assets ?>nyckml.js" defer></script>
<?php if ($embed): ?>
<script src="<?= $assets ?>embed.js" data-parent-origin="<?= h($embed_parent_origin) ?>" defer></script>
<?php endif ?>
</head>

<body>

<h1>NYC Building KML Tool</h1>

<?php if ($errors): ?>
<div class="errors">
<?php foreach ($errors as $error): ?>
	<p><?= h($error) ?></p>
<?php endforeach ?>
</div>
<?php endif ?>

<form method="get">

<?php if ($step === 1): ?>

<?php foreach ([1 => 'first', 2 => 'second'] as $n => $which): ?>
<p>Enter the <?= $which ?> address: <input name="input_address_<?= $n ?>" value="<?= h(param("input_address_$n")) ?>">
or an install number: <input name="input_install_<?= $n ?>" inputmode="numeric" size="8" value="<?= h(param("input_install_$n")) ?>"></p>
<?php endforeach ?>
<p><button type="submit" name="step" value="2">Next</button></p>

<?php else: ?>

<div class="endpoints">
<?php foreach ($endpoints as $n => $endpoint): ?>
<fieldset class="endpoint">
	<legend>Endpoint #<?= $n ?></legend>
	<input type="hidden" name="input_address_<?= $n ?>" value="<?= h(param("input_address_$n")) ?>">
	<input type="hidden" name="input_install_<?= $n ?>" value="<?= h(param("input_install_$n")) ?>">
	<input type="hidden" name="address_<?= $n ?>" value="<?= h($endpoint['address']) ?>">
	<input type="hidden" name="bin_<?= $n ?>" value="<?= h($endpoint['bin']) ?>">
	<input type="hidden" id="lat_<?= $n ?>" name="lat_<?= $n ?>" value="<?= h($endpoint['lat']) ?>">
	<input type="hidden" id="long_<?= $n ?>" name="long_<?= $n ?>" value="<?= h($endpoint['long']) ?>">

	<p class="entered">You entered <?= $endpoint['install'] !== '' ? 'install number ' . h($endpoint['install']) : 'address ' . h(param("input_address_$n")) ?></p>
	<p>Matched Address: <?= h($endpoint['address']) ?></p>
	<?php if ($step === 2 && !empty($endpoint['alternatives'])): ?>
	<p class="note">Not the right one? Other matches:
	<?php foreach ($endpoint['alternatives'] as $i => $match): ?>
		<?= $i ? '&middot;' : '' ?> <a href="<?= h(pick_match_url($n, $match)) ?>"><?= h(strtok($match['address'], ',') . ', ' . $match['borough'] . ' ' . $match['zip']) ?></a>
	<?php endforeach ?>
	</p>
	<?php endif ?>
	<p>BIN: <?= h($endpoint['bin'] ?: 'none') ?></p>
	<p>Coordinates: <span id="coords_<?= $n ?>"><?= h($endpoint['lat']) ?>, <?= h($endpoint['long']) ?></span>
	<?php if ($step === 2): ?><a href="#" class="reset" data-endpoint="<?= $n ?>">(reset)</a><?php endif ?></p>

	<?php if ($step === 2): ?>
	<div id="map_<?= $n ?>" class="map"></div>
	<p>Where do you want to get the height from?<br>
		<label><input type="radio" name="height_radio_<?= $n ?>" value="dob" <?= param("height_radio_$n") === 'dob' ? 'checked' : '' ?>> DOB</label>
		<label><input type="radio" name="height_radio_<?= $n ?>" value="db" <?= param("height_radio_$n") === 'db' ? 'checked' : '' ?>> DB</label>
		or <input type="number" name="height_manual_<?= $n ?>" min="0" step="any" placeholder="Manual entry (meters above ground)" value="<?= h(param("height_manual_$n")) ?>">
	</p>
	<?php else: ?>
	<input type="hidden" name="height_radio_<?= $n ?>" value="<?= h(param("height_radio_$n")) ?>">
	<input type="hidden" name="height_manual_<?= $n ?>" value="<?= h(param("height_manual_$n")) ?>">
	<p>Height: <?= format_height($endpoint['altitude'] - $endpoint['ground']) ?> above ground
		<span class="note">(<?= number_format($endpoint['altitude'], 1) ?> m above sea level, <?= h($endpoint['height_source']) ?>)</span></p>
	<?php endif ?>
</fieldset>
<?php endforeach ?>
</div>

<?php if ($step === 2): ?>
<p class="note">DOB: roof height from NYC Open Data Building Footprints<br>
DB: altitude of building object stored in MeshDB<br>
Manual: meters above ground level that will override DOB and DB selections</p>
<p><button type="submit" name="step" value="3">Next</button></p>
<?php else: ?>
<input type="hidden" name="step" value="3">
<div id="overview_map" class="map"></div>
<p class="link-stats">
	Distance: <?= number_format($link['distance']) ?> m (<?= number_format($link['distance'] / 1609.344, 2) ?> mi)
	&middot; Azimuth: <?= number_format($link['azimuth_1'], 1) ?>&deg; from #1, <?= number_format($link['azimuth_2'], 1) ?>&deg; from #2
	&middot; Elevation angle from #1: <?= number_format($link['elevation'], 2) ?>&deg;
</p>

<p>I am ready to export the KML. Select the options you want and let's do this!</p>
<p>Line Color: <input type="text" id="color" name="color" value="<?= h($color) ?>" data-coloris></p>
<p>Fresnel frequency: <input type="number" id="frequency" name="frequency" min="1" max="100000" step="any" value="<?= h($frequency) ?>"> MHz,
zone: <input type="number" name="zone" min="0.5" max="6" step="0.1" value="<?= h($zone) ?>"></p>
<p class="actions">
	<button type="submit" name="download" value="1">Download KML</button>
	<button type="submit" name="fresnel" value="1">Download KML with Fresnel</button>
	<button type="button" id="display">Display KML</button>
	<label><input type="checkbox" id="show_fresnel" checked> show 1st Fresnel zone</label>
</p>
<div id="preview" class="preview" hidden></div>
<p id="preview_note" class="note" hidden></p>
<?php endif ?>

<?php endif ?>

</form>

<h2>
<?php if ($back_url): ?><button type="button" id="go_back" data-href="<?= h($back_url) ?>">Go Back</button><?php endif ?>
<button type="button" id="start_over">Start Over</button>
</h2>

<p><a href="https://github.com/nycmeshnet/nyckml" target="_blank" rel="noopener">https://github.com/nycmeshnet/nyckml</a></p>

</body>

</html>
