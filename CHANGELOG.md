## Next Release

BREAKING CHANGES:

FEATURES:
  * command/weburg-download: download single movie by url or id without popularity check
  * command/weburg-download: download single series for last x days by url or id without popularity check

IMPROVEMENTS:
  * command/stats-send: broken connection handling and logging
  * core: 100% test coverage

BUG FIXES:

## 0.3.1 (February 28, 2016)

FEATURES:
  * **weburg-series-add**: Add series to monitoring list

BUG FIXES:
  * command/weburg-download: fixed false warning 'Cannot find all information about movie'

## 0.3.0 (February 28, 2016)

BREAKING CHANGES:

  * TransmissionClient constructor changed
  * option --host renamed to --transmission-host
  * option --dest renamed to --download-torrents-dir
  * command download-weburg renamed to weburg-download
  * command send-metrics renamed to stats-send
  * command remove-duplicates renamed to torrent-remove-duplicates
  * command list-torrents renamed to torrent-list
  * command clean-torrents renamed to torrent-clean

FEATURES:
  * command/weburg-download: add download-votes-min config parameter
  * core: add config file

IMPROVEMENTS:
  * core: Config, TransmissionClient and WeburgClient fully tested
  * core: extracted WeburgClient class and fully tested
  * command/weburg-download: command rewrited, fully tested
  * command/weburg-download: add logging of corrupted movie info
  * command/list-torrents: partially tested
  * command/remove-duplicates: partially tested

BUG FIXES:
  * command/weburg-download: fixed dry-run option
  * command/weburg-download: fixed imdb rating bypass

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
