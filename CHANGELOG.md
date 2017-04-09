<a name=""></a>
# [](https://github.com/popstas/transmission-cli/compare/v0.7.0...v) (2017-04-09)


### Bug Fixes

* **torrent-remove-duplicates:** confirmation remove duplicates ([5fca77c](https://github.com/popstas/transmission-cli/commit/5fca77c)), closes [#38](https://github.com/popstas/transmission-cli/issues/38)


### Features

* **torrent-remove-duplicates:** config option `allow-duplicates` ([32ccc56](https://github.com/popstas/transmission-cli/commit/32ccc56)), closes [#38](https://github.com/popstas/transmission-cli/issues/38)


### BREAKING CHANGES

* torrent-remove-duplicates: `torrent-remove-duplicates` command now default interactive and requires confirmation.
For using in cron you should use `-y` flag in almost all commands.



<a name="0.7.0"></a>
# [0.7.0](https://github.com/popstas/transmission-cli/compare/v0.6.0...v0.7.0) (2017-04-09)


### Bug Fixes

* **api:** update addTorrent to martial/transmission-api 1.1.6 ([4e9e3a6](https://github.com/popstas/transmission-cli/commit/4e9e3a6))
* **api:** update getFreeSpace to martial/transmission-api 1.1.5 ([9c88318](https://github.com/popstas/transmission-cli/commit/9c88318))
* **api:** upgrade to martial/transmission-api 2.0 ([d53503f](https://github.com/popstas/transmission-cli/commit/d53503f))
* **stats:** correct database creation for influxdb 1.0+ ([5b8eaf2](https://github.com/popstas/transmission-cli/commit/5b8eaf2))
* composer update ([d6555f1](https://github.com/popstas/transmission-cli/commit/d6555f1))
* create default config in homedir on first run ([0f0e6dd](https://github.com/popstas/transmission-cli/commit/0f0e6dd)), closes [#35](https://github.com/popstas/transmission-cli/issues/35)


### Features

* **api:** getDownloadDir, getFreeSpace ([3ac3386](https://github.com/popstas/transmission-cli/commit/3ac3386))
* **stats-get, torrent-list:** show available free disk space ([a0d5071](https://github.com/popstas/transmission-cli/commit/a0d5071))
* Remove duplicates after torrent-add ([b6b7ad6](https://github.com/popstas/transmission-cli/commit/b6b7ad6)), closes [#transmission-cli-31](https://github.com/popstas/transmission-cli/issues/transmission-cli-31)



<a name="0.6.0"></a>
# [0.6.0](https://github.com/popstas/transmission-cli/compare/v0.5.0...v0.6.0) (2016-03-07)


### Bug Fixes

* notices on add duplicating torrent ([ec101e9](https://github.com/popstas/transmission-cli/commit/ec101e9)), closes [#34](https://github.com/popstas/transmission-cli/issues/34)
* torrent-stat and torrent-list correct totals when limit ([9ff4ca4](https://github.com/popstas/transmission-cli/commit/9ff4ca4)), closes [#21](https://github.com/popstas/transmission-cli/issues/21)
* torrent-stat and torrent-list correct totals when limit ([6e7f584](https://github.com/popstas/transmission-cli/commit/6e7f584)), closes [#21](https://github.com/popstas/transmission-cli/issues/21)


### Features

* **_docs:** Command for generate docs/commands.md with commands help ([b635820](https://github.com/popstas/transmission-cli/commit/b635820)), closes [#32](https://github.com/popstas/transmission-cli/issues/32)
* **command:** command help render markdown code blocks ([d863814](https://github.com/popstas/transmission-cli/commit/d863814))
* **torrent-add:** add command for add torrent by file or url ([2515f9c](https://github.com/popstas/transmission-cli/commit/2515f9c)), closes [#4](https://github.com/popstas/transmission-cli/issues/4)
* multiple transmission instances in config ([ef90596](https://github.com/popstas/transmission-cli/commit/ef90596)), closes [#11](https://github.com/popstas/transmission-cli/issues/11)
* **weburg-download:** add downloaded torrents with torrent-add command ([7229c34](https://github.com/popstas/transmission-cli/commit/7229c34)), closes [#30](https://github.com/popstas/transmission-cli/issues/30)


### BREAKING CHANGES

* change Transmission config names

Before:
```
transmission-host: localhost
 transmission-port: 9091
 transmission-user: ''
 transmission-password: ''
```

After:
```
transmission:
    - { host: localhost, port: 9091, user: '', password: '' }
    - { host: host2, port: 9091, user: '', password: '' }
```

You can automatically add config in new format by add any series to list:
transmission-cli weburg-series-add 12345



## 0.5.0 (March 5, 2016)

BREAKING CHANGES:
  * stats-send: removed --influxdb-* command-line options
  * grafana dashboards at this time show incorrect data

FEATURES:
  * packagist.org package: now able to install with composer (but recommended PHAR installation)
  * torrent-list: add columns 'Age', 'Uploaded', 'Per day', add --sort, --age, --name, --limit options
  * **stats-get** - get metrics from InfluxDB and remove filtered torrents, --sort --name --profit --limit --days --rm options 
  * **torrent-remove** - remove torrents by torrent id

IMPROVEMENTS:
  * stats-send: now it send derivative uploaded value from last send
  * core: extracted InfluxDbClient
  * removed unused torrent-clean command

BUG FIXES:
  * fixed incorrect display sizes in GB

## 0.4.1 (March 2, 2016)

BUG FIXES:
  * fixed bug #9, some tracked series was not downloaded

## 0.4.0 (February 29, 2016)

FEATURES:
  * command/weburg-download: download single movie by url or id without popularity check
  * command/weburg-download: download single series for last x days by url or id without popularity check
  * **command/weburg-download**: now weburg-download also downloads new series for torrents added through weburg-series-add.
    You can select command behaviour by --popular and --series flags if you want to download something one.

IMPROVEMENTS:
  * command/stats-send: broken connection handling and logging
  * core: 100% test coverage
  * core: config anymore not creating if all parameters are default
  * core: added phpmd and scrutinizer metrics for better code quality
  * WeburgClient: add request delay between requests, default 2 seconds, changes by config parameter weburg-request-delay

BUG FIXES:
  * many possibly bugs fixed while code coverage and follow phpmd and scrutinizer hints

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
