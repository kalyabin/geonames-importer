# Geonames.org importer API
CSV data importer from geonames.org

Use open CSV base from geonames:
- cities bases (cities5000.zip, cities15000.zip, RU.zip, US.zip, etc.)
- countries base
- states base (admin1code.csv)

## Installation

Run in your console
```bash
php composer.phar require "kalyabin/geonames-importer" "dev-master"
```

## Import all countries

```php
$importer = new \kalyabin\geonames\importer\Country('/tmp/', function($country) {
    print 'Consume country: ' . "\n";
    print_r($country);
    print "\n";
    // do something else
});
$importer->process();
```

## Import cities

```php
$importer = new \kalyabin\geonames\importer\City('/tmp/', 'cities5000.zip', function($city) {
    print 'Consume city: ' . "\n";
    print_r($city);
    print "\n";
    // do something else
});
$importer->process();
```
You may type second param like RU.zip, US.zip, cities15000.zip, etc.

## Import states

```php
$importer = new \kalyabin\geonames\importer\Admin1CodeASCII('/tmp/', function($region) {
    fwrite(STDOUT, "Consume region: ");
    print_r($region);
    fwrite(STDOUT, "\n");
});
$importer->process();
```

More about geonames open base read at http://download.geonames.org/export/dump/readme.txt 
