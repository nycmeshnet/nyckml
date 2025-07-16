// For handling embed within dantonio.tech Win95 interface
document.addEventListener('click', function() {
    // Send a message to the parent window
    window.parent.postMessage("switchTo", "https://dantonio.tech");
});

function goBack() {
    if (window.history && window.history.length > 1) {
        // If the iframe's history has more than one entry, go back
        window.history.back();
    } else {
        // If there's only one entry or no history, you might want to handle it differently
        console.log("Cannot go back within the iframe's history.");
    }
}

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
