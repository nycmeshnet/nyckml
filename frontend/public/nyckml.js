// Browser side of the NYC Building KML Tool: the draggable endpoint maps (step 2), the link overview map and the
// 3D preview (step 3). The page hands over what it looked up in the #nyckml-data JSON block.
(function () {
	const IMAGERY_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
	const IMAGERY_ATTRIBUTION = 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';
	const CESIUM_URL = 'https://cdn.jsdelivr.net/npm/cesium@1.146.0/Build/Cesium/';
	const CESIUM_JS_INTEGRITY = 'sha384-1lWK5deN/B+lLaUw4lk6xKp/L19ii/98SZLk6K8YthDskgvc+GbdixLzbCnSS2Wn';
	const CESIUM_CSS_INTEGRITY = 'sha384-ghEeMdcWWzRv/BPeUcX835vcKDGrxvROXisl/Btpv3GeekBUXTSPVcFJpI1Tcrgp';
	// Our altitudes are above sea level, but Cesium and Google's 3D tiles measure from the WGS84 ellipsoid. Across
	// NYC the geoid sits 31.3 to 32.3 m below the ellipsoid (NOAA GEOID18), so one constant is close enough here.
	const GEOID_HEIGHT = -31.7;

	const data = JSON.parse(document.getElementById('nyckml-data').textContent);

	function imageryLayer() {
		return L.tileLayer(IMAGERY_URL, {attribution: IMAGERY_ATTRIBUTION, maxZoom: 21, maxNativeZoom: 20});
	}

	// Step 2: drag the marker to fine-tune where an endpoint is.
	function setupEndpointMap(n, endpoint) {
		const lookedUp = L.latLng(endpoint.lat, endpoint.long);
		const map = L.map('map_' + n, {center: lookedUp, zoom: 20, layers: [imageryLayer()]});
		const marker = L.marker(lookedUp, {draggable: true, autoPan: true}).addTo(map);

		function setCoords(latlng) {
			const lat = latlng.lat.toFixed(7);
			const long = latlng.lng.toFixed(7);
			document.getElementById('coords_' + n).textContent = lat + ', ' + long;
			document.getElementById('lat_' + n).value = lat;
			document.getElementById('long_' + n).value = long;
		}

		marker.on('dragend', function () {
			setCoords(marker.getLatLng());
		});
		document.querySelector('.reset[data-endpoint="' + n + '"]').addEventListener('click', function (e) {
			e.preventDefault();
			marker.setLatLng(lookedUp);
			map.panTo(lookedUp);
			setCoords(lookedUp);
		});
	}

	// Step 3: both endpoints and the line between them, in the chosen color.
	function setupOverviewMap(a, b, colorInput) {
		const map = L.map('overview_map', {layers: [imageryLayer()]});
		const line = L.polyline([[a.lat, a.long], [b.lat, b.long]], {color: colorInput.value, weight: 3}).addTo(map);
		L.marker([a.lat, a.long]).bindTooltip('#1').addTo(map);
		L.marker([b.lat, b.long]).bindTooltip('#2').addTo(map);
		map.fitBounds(line.getBounds(), {padding: [30, 30], maxZoom: 19});
		colorInput.addEventListener('input', function () {
			line.setStyle({color: colorInput.value});
		});
	}

	// ---- 3D preview ----------------------------------------------------------------------------------------------

	let cesiumLoading = null;
	let viewer = null;

	// Cesium is a few MB, so it's only fetched the first time someone asks for the preview.
	function loadCesium() {
		if (!cesiumLoading) {
			window.CESIUM_BASE_URL = CESIUM_URL;
			const css = document.createElement('link');
			css.rel = 'stylesheet';
			css.href = CESIUM_URL + 'Widgets/widgets.css';
			css.integrity = CESIUM_CSS_INTEGRITY;
			css.crossOrigin = 'anonymous';
			document.head.appendChild(css);
			cesiumLoading = new Promise(function (resolve, reject) {
				const script = document.createElement('script');
				script.src = CESIUM_URL + 'Cesium.js';
				script.integrity = CESIUM_JS_INTEGRITY;
				script.crossOrigin = 'anonymous';
				script.onload = resolve;
				script.onerror = function () {
					cesiumLoading = null;
					reject(new Error("Couldn't load the 3D viewer."));
				};
				document.head.appendChild(script);
			});
		}
		return cesiumLoading;
	}

	async function createViewer(container, note) {
		const viewer = new Cesium.Viewer(container, {
			baseLayer: new Cesium.ImageryLayer(new Cesium.UrlTemplateImageryProvider({
				url: IMAGERY_URL,
				credit: 'Esri World Imagery',
				maximumLevel: 20,
			})),
			baseLayerPicker: false,
			geocoder: false,
			animation: false,
			timeline: false,
			sceneModePicker: false,
			navigationHelpButton: false,
			homeButton: false,
			infoBox: false,
			selectionIndicator: false,
		});
		if (!data.cesiumIonToken) {
			note('No 3D buildings: the server has no CESIUM_ION_TOKEN set, so this is flat imagery only.');
			return viewer;
		}
		Cesium.Ion.defaultAccessToken = data.cesiumIonToken;
		try {
			viewer.scene.primitives.add(await Cesium.createGooglePhotorealistic3DTileset());
			viewer.scene.globe.show = false;  // the 3D tiles include their own ground
		} catch (err) {
			console.error(err);
			note("Couldn't load the 3D buildings, so this is flat imagery only.");
		}
		return viewer;
	}

	// The first Fresnel zone: an ellipsoid stretched along the path, widest (½·√(λ·d)) at the midpoint.
	function fresnelZone(start, end, frequencyMhz, color) {
		const C = Cesium.Cartesian3;
		const distance = C.distance(start, end);
		const wavelength = 299792458 / (frequencyMhz * 1e6);
		const radius = 0.5 * Math.sqrt(wavelength * distance);
		const center = C.midpoint(start, end, new C());
		const along = C.normalize(C.subtract(end, start, new C()), new C());
		const up = Cesium.Ellipsoid.WGS84.geodeticSurfaceNormal(center, new C());
		const side = C.normalize(C.cross(up, along, new C()), new C());
		const top = C.cross(along, side, new C());
		// Columns are where the ellipsoid's own x, y and z axes end up.
		const rotation = new Cesium.Matrix3(
			along.x, side.x, top.x,
			along.y, side.y, top.y,
			along.z, side.z, top.z
		);
		return {
			position: center,
			orientation: Cesium.Quaternion.fromRotationMatrix(rotation),
			ellipsoid: {radii: new C(distance / 2, radius, radius), material: color.withAlpha(0.2)},
		};
	}

	// Draws the same thing the KML describes: a line between the rooftops, extruded down to the ground.
	function drawLink(viewer, a, b, cssColor, frequencyMhz, showFresnel) {
		const color = Cesium.Color.fromCssColorString(cssColor);
		const top = function (e) {
			return Cesium.Cartesian3.fromDegrees(e.long, e.lat, e.altitude + GEOID_HEIGHT);
		};
		viewer.entities.removeAll();
		viewer.entities.add({
			wall: {
				positions: [top(a), top(b)],
				minimumHeights: [a.ground + GEOID_HEIGHT, b.ground + GEOID_HEIGHT],
				material: color.withAlpha(0.25),
			},
		});
		viewer.entities.add({polyline: {positions: [top(a), top(b)], width: 3, material: color}});
		[a, b].forEach(function (e, i) {
			viewer.entities.add({
				position: top(e),
				point: {pixelSize: 10, color: color, outlineColor: Cesium.Color.BLACK, outlineWidth: 2, disableDepthTestDistance: Infinity},
				label: {
					text: '#' + (i + 1) + ' ' + e.name,
					font: '14px sans-serif',
					style: Cesium.LabelStyle.FILL_AND_OUTLINE,
					outlineWidth: 3,
					pixelOffset: new Cesium.Cartesian2(0, -18),
					disableDepthTestDistance: Infinity,
				},
			});
		});
		if (showFresnel && frequencyMhz > 0) {
			viewer.entities.add(fresnelZone(top(a), top(b), frequencyMhz, color));
		}
		viewer.zoomTo(viewer.entities, new Cesium.HeadingPitchRange(0, Cesium.Math.toRadians(-25), 0));
	}

	function setupPreview(a, b) {
		const button = document.getElementById('display');
		const container = document.getElementById('preview');
		const noteEl = document.getElementById('preview_note');
		function note(text) {
			noteEl.textContent = text;
			noteEl.hidden = !text;
		}
		button.addEventListener('click', async function () {
			button.disabled = true;
			container.hidden = false;
			try {
				await loadCesium();
				viewer = viewer || await createViewer(container, note);
				drawLink(
					viewer, a, b,
					document.getElementById('color').value,
					parseFloat(document.getElementById('frequency').value),
					document.getElementById('show_fresnel').checked
				);
				button.textContent = 'Refresh 3D view';
			} catch (err) {
				console.error(err);
				note(err.message);
			}
			button.disabled = false;
		});
	}

	// ---- Wiring --------------------------------------------------------------------------------------------------

	document.getElementById('go_back').addEventListener('click', function () {
		history.back();
	});
	document.getElementById('start_over').addEventListener('click', function () {
		window.location.href = window.location.pathname;
	});

	if (data.step === 2) {
		setupEndpointMap(1, data.endpoints[1]);
		setupEndpointMap(2, data.endpoints[2]);
	}
	if (data.step === 3) {
		Coloris({el: '#color', alpha: false, format: 'hex'});
		setupOverviewMap(data.endpoints[1], data.endpoints[2], document.getElementById('color'));
		setupPreview(data.endpoints[1], data.endpoints[2]);
	}
})();
