## Next Release

BREAKING CHANGES:

  * TransmissionClient constructor changed


FEATURES:
  * command/download-weburg: add download-votes-min config parameter
  * core: add config file

IMPROVEMENTS:
  * command/download-weburg: command rewrited, extracted WeburgClient class and fully tested
  * core: Config, TransmissionClient and WeburgClient fully tested
  * command/list-torrents: partially tested
  * command/remove-duplicates: partially tested

BUG FIXES:
  * command/download-weburg: fixed dry-run option
  * command/download-weburg: fixed imdb rating bypass

## 0.2.1 (January 17, 2016)

BUG FIXES:
  * core: Fix fatal error in RpcClient logger

## 0.2.0 (January 17, 2016)

BREAKING CHANGES:

  * InfluxDB default host changed to localhost

FEATURES:

  * command/all: add option --dry-run
  * app: add logging
  
IMPROVEMENTS:

  * core: reduce size of PHAR
  * grafana: show all hosts summary together
  * code: psr-2 standards 

BUG FIXES:

## 0.1.1 (January 13, 2016)

IMPROVEMENTS:

  * core: distribution with PHAR
  * code: add Travis CI integration
  * code: add phpunit

BUG FIXES:

  * command/remove-duplicates: now works

## 0.1.0 (January 11, 2016)

FEATURES:

  * **download-weburg**: Download torrents from weburg.net.
  * **list-torrents**: Just list torrents from Transmission.
  * **remove-duplicates**: Remove obsolete series movie uploads
  * **send-metrics**: Send metrics to InfluxDB
