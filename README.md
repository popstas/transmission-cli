# Transmission CLI [![Build Status](https://travis-ci.org/popstas/transmission-cli.svg?branch=master)](https://travis-ci.org/popstas/transmission-cli) [![Coverage Status](https://coveralls.io/repos/github/popstas/transmission-cli/badge.svg?branch=master)](https://coveralls.io/github/popstas/transmission-cli?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/popstas/transmission-cli/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/popstas/transmission-cli/?branch=master) 

transmission-cli is console php application for automate torrent download/upload from weburg.net

Blog post: http://blog.popstas.ru/blog/2016/01/17/torrent-transmission-client-for-weburg/

Based on:

- [symfony/console](http://symfony.com/doc/current/components/console/index.html),
- [martial/transmission-api](https://github.com/MartialGeek/transmission-api),
- [leominov/weburg-torrent-grabber](https://github.com/leominov/weburg-torrent-grabber).

# Features
- send metrics to InfluxDB, torrent monitoring trends with Grafana
- download popular (rating, comment and votes based) movies from http://weburg.net
- download tracked series from http://weburg.net
- delete not popular uploads from transmission
- working with multiple transmission instances
- command line autocompletion

## Graphics

- hourly upload stats by torrent and summary upload
- list all hosts that sends metrics to InfluxDB

![Screenshot](doc/img/grafana.png?raw=true)


# Available commands:
- `help`                             - Displays help for a command
- `list`                             - List commands
- `torrent-list [--name='series*1080'] [--age='>1 <3 =2'] [--sort=1] [--limit=10]`, `tl` - List, filter and sort torrents
- `torrent-add file|url [file2] [fileX]`, `ta` - Safe add torrents
- `torrent-remove 1 [2] [3]`, `tr`    - Remove one or more torrents by torrent id
- `torrent-remove-duplicates`, `trd` - Remove duplicates obsolete torrents
- `stats-get [--name='name'] [--age='>1 <3 =2'] [profit='>0'] [--days=7] [--sort=1] [--limit=10] [--rm]`, `sg` - Get metrics from InfluxDB
- `stats-send`, `ss`                 - Send metrics to InfluxDB
- `weburg-download`, `wd`            - Download popular torrents and tracked series from weburg.net
- `weburg-download --popular`        - Download only popular
- `weburg-download --series`         - Download only series
- `weburg-download [movie url or id]` - Download movie without popularity check
- `weburg-download [series url or id] [--days=1]` - Download series for last x days
- `weburg-series-add`, `wsa`         - Add series to monitoring list

#### Global command options
- `--config` - set path to config file
- `--dry-run` - don't change any data
- `-v|vv|vvv` - more verbose output
- `--transmission-host` - set transmission host
- `--transmission-port` - set transmission port
- `--transmission-user` - set transmission user
- `--transmission-password` - set transmission password


# Install

### PHAR automatic (Recommended)
```
latest_phar=$(curl -s https://api.github.com/repos/popstas/transmission-cli/releases/latest | grep 'browser_' | cut -d\" -f4)
wget -O /usr/local/bin/transmission-cli "$latest_phar"
chmod +x /usr/local/bin/transmission-cli
```

### PHAR manually
Download latest transmission-cli.phar [here](https://github.com/popstas/transmission-cli/releases/latest), make it executable and put it to bin directory.

### Composer global
```
composer global require popstas/transmission-cli
```
If you cannot execute `transmission-cli` after that, probably you should add ~/.composer/vendor/bin to your PATH environment variable
as described [here](https://akrabat.com/global-installation-of-php-tools-with-composer/).
 

### Composer from source:
```
git clone https://github.com/popstas/transmission-cli
cd transmission-cli
composer install
ln -s "$PWD"/bin/transmission-cli /usr/local/bin/transmission-cli
```

# Configure
Default config placement: `~/.transmission-cli.yml`. It creates on first `weburg-series-add` command.
You can change some parameters here.

Also, you can pass config to command: `transmission-cli --config /path/to/config.yml`

Commands `weburg-download`, `weburg-series-add`, interacts only with weburg.net and not requests to Transmission or InfluxDb.


#### Transmission
If you want to make commands `torrent-*` working, you should enable remote access in Transmission
and add host, port, username, password if it not defaults.

By default, transmission-cli request to Transmission on localhost:9091 without user and password. You can change it in `~/.transmission-cli.yml`.

You can override default config: `--transmission-host`, `--transmission-port`, `--transmission-user`, `--transmission-password`

Also, maybe you want to automatically download movies, not only torrent files. To do that, enable autodownload in Transmission
and point to same directory in `--dest=` option.


#### InfluxDB and Grafana
You need to install it for drawing torrent graphics.

**InfluxDB**

Simplest way to install InfluxDB - Docker:
```
docker run --name influxdb\
    -d --volume=/Users/popstas/lib/influxdb:/data \
    -p 8083:8083 -p 8086:8086 \
    tutum/influxdb
```
And if you don't want to see detailed stats about your torrents, you may not install InfluxDB, commands `stat-*` will not working.


**Grafana**

Add InfluxDB as data source to Grafana.
Then import dashboard - [grafana-torrents.json](doc/grafana-torrents.json)
If you don't want to see graphs, Grafana not necessary.

#### [autocompletion](https://github.com/stecman/symfony-console-completion) for bash/zsh:
```
source <(transmission-cli _completion --generate-hook)
```


#### Cron
Then, add to cron tasks like this:
```
PATH="$PATH:/usr/local/bin"
59 * * * * transmission-cli torrent-remove-duplicates --transmission-host=localhost
59 * * * * transmission-cli torrent-remove-duplicates --transmission-host=wrtnsq
0  * * * * transmission-cli stats-send --transmission-host=localhost
0  * * * * transmission-cli stats-send --transmission-host=wrtnsq
1  2 * * * transmission-cli weburg-download --download-torrents-dir=/Volumes/media/_planeta/_torrents
```
Don't forget add to cron PATH your ~/.composer/vendor/bin if you installed transmission-cli with `composer global`!

# Usage

See commands `--help`:

- `torrent-list --help`
- `torrent-add --help`
- `torrent-remove --help`
- `torrent-remove-duplicates --help`
- `stats-get --help`
- `stats-send --help`
- `weburg-download --help`
- `weburg-series-add --help`


# Contribution

### Required checks for pass build:
```
./vendor/bin/phpcs --standard=psr2 ./src ./tests
phpunit
```

### Recommended
- support code full coverage
- check code with PHP Mess Detector:hich 
```
phpmd src/ text codesize,controversial,design,naming,unusedcode
```
