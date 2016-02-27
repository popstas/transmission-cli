# Transmission CLI [![Build Status](https://travis-ci.org/popstas/transmission-cli.svg?branch=master)](https://travis-ci.org/popstas/transmission-cli) [![Coverage Status](https://coveralls.io/repos/github/popstas/transmission-cli/badge.svg?branch=master)](https://coveralls.io/github/popstas/transmission-cli?branch=master)

transmission-cli is console php application for automate torrent download/upload from weburg.net

Blog post: http://blog.popstas.ru/blog/2016/01/17/torrent-transmission-client-for-weburg/

Based on:

- [symfony/console](http://symfony.com/doc/current/components/console/index.html),
- [martial/transmission-api](https://github.com/MartialGeek/transmission-api),
- [leominov/weburg-torrent-grabber](https://github.com/leominov/weburg-torrent-grabber).

# Features
- send metrics to InfluxDB, torrent monitoring trends with Grafana
- download popular (rating, comment and votes based) movies from http://weburg.net
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
- `torrent-clean`, `tc`              - Clean not popular torrents
- `torrent-list`, `tl`               - List torrents
- `torrent-remove-duplicates`, `trd` - Remove duplicates obsolete torrents
- `stats-send`, `ss`                 - Send metrics to InfluxDB
- `weburg-download`, `wd`            - Download torrents from weburg.net
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
Download latest transmission-cli.phar [here](https://github.com/popstas/transmission-cli/releases/latest)
make it executable and put it to bin directory:
```
latest_phar=$(curl -s https://api.github.com/repos/popstas/transmission-cli/releases/latest | grep 'browser_' | cut -d\" -f4)
wget -O /usr/local/bin/transmission-cli "$latest_phar"
chmod +x /usr/local/bin/transmission-cli
```

Or build with composer:
```
git clone https://github.com/popstas/transmission-cli
cd transmission-cli
composer install
ln -s "$PWD"/bin/transmission-cli /usr/local/bin/transmission-cli
```

#### Configure
Default config placement: `~/.transmission-cli.yml`. It creates on first application run.
You can change some parameters here.
Also, you can pass config to command: `transmission-cli --config /path/to/config.yml`


#### Transmission
You need to enable remote access in Transmission
and add host, port, username, password if it not defaults.
You can change it in `~/.transmission-cli.yml`.
You can override default config: `--transmission-host`, `--transmission-port`, `--transmission-user`, `--transmission-password`

#### InfluxDB and Grafana
You need to install it for drawing torrent graphics.

**Influxdb**

Add host, port and database name in InfluxDB to config.

**Grafana**

Add InfluxDB as data source to Grafana.
Then import dashboard - [grafana-torrents.json](doc/grafana-torrents.json)

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


# Check code style
```
 ./vendor/bin/phpcs --standard=psr2 ./src ./tests
 ./vendor/bin/phpmd src/ text codesize,controversial,design,naming,unusedcode
```

# TODO:
- test phar
- docs
- packagist
- track deleted torrents
- more strict variable naming
