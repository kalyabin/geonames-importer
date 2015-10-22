# Geonames.org importer API
CSV data importer from geonames.org

Use open CSV base from geonames:
- cities bases (cities5000.zip, cities15000.zip, RU.zip, US.zip, etc.)
- countries base

## Installation

Run in your console
```bash
php composer.phar require "kalyabin/geonames-importer" "dev-master"
```

## Basic usage

To import all countries use:
```php
$importer = new \kalyabin\geonames\importer\Country('/tmp/', function($country) {
    print 'Consume country: ' . "\n";
    print_r($country);
    print "\n";
    // do something else
});
$importer->process();
```

To import cities use:
```php
$importer = new \kalyabin\geonames\importer\City('/tmp/', 'cities5000.zip', function($city) {
    print 'Consume city: ' . "\n";
    print_r($city);
    print "\n";
    // do something else
});
$importer->process();
```

More about geonames open base read at http://download.geonames.org/export/dump/readme.txt
For export cities data you may type second param like RU.zip, US.zip, cities15000.zip, etc. 
