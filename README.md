# NYC Building KML Tool
Takes two NYC addresses and step-by-step gets data from NYC databases provided by Department of City Planning and Department of Buildings. Generates a KML with a solid line extended to ground designed for viewing in a program like Google Earth.

## APIs Used ##

Typed Address to Real Address, BINs, and Coords https://geosearch.planninglabs.nyc/docs/

BIN to Height https://dev.socrata.com/foundry/data.cityofnewyork.us/7w4b-tj9d

## How to Run ##

File can be run on any PHP instance that has access to internet and `allow_url_fopen` is enabled in your `php.ini` due to the use of the `file_get_contents` function.

## See it Live ##

Currently running on TORTOISE. Subject to breakage or removal at any time. https://dantonio.tech/programs/nyckml/
