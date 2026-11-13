# NYC Building KML Tool
Takes two NYC addresses or NYC Mesh install numbers and step-by-step gets data from NYC databases provided by Department of City Planning and Department of Buildings. Generates a KML with a solid line extended to ground designed for viewing in a program like Google Earth. Can also download a Fresnel zone KML from radiofresnel.com or show the link in a 3D view in the browser.

Heights can come from DOB (NYC building footprints), DB (MeshDB) or be entered manually. They all get converted to meters above sea level the same way MeshDB does it (roof height + ground elevation from the footprints dataset), so the KML always uses the `absolute` altitude mode.

## APIs Used ##

Typed Address to Real Address, BINs, and Coords https://geosearch.planninglabs.nyc/docs/

BIN to Roof Height and Ground Elevation https://data.cityofnewyork.us/City-Government/Building-Footprints/5zhs-2jue/about_data

Install Number to Building and Altitude https://db.nycmesh.net/api/v1/

Ground Elevation when No Footprint Available https://epqs.nationalmap.gov/v1/docs

## Layout ##

`frontend/src/`: `app.php` is the page and `lib.php` is the API calls and KML generation

`frontend/public/`: web root

`infra/nyckml-helm/`: Helm chart for the deploy workflow

## How to Run ##

```
docker build -t nyckml frontend
docker run -p 8080:80 -e MESHDB_KEY=... -e CESIUM_ION_TOKEN=... nyckml
```

Environment variables:

- `MESHDB_KEY`: MeshDB API token for install numbers and DB heights
- `CESIUM_ION_TOKEN`: Cesium ion token for the 3D buildings in the preview

## See it Live ##

https://nyckml.mesh.nycmesh.net/