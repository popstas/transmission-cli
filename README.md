# Transmission CLI [![Build Status](https://travis-ci.org/popstas/transmission-cli.svg?branch=master)](https://travis-ci.org/popstas/transmission-cli) [![Coverage Status](https://coveralls.io/repos/popstas/transmission-cli/badge.svg?branch=master&service=github)](https://coveralls.io/github/popstas/transmission-cli?branch=master)

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
- `download-weburg`    Download torrents from weburg.net
- `help`               Displays help for a command
- `list`               List commands
- `list-torrents`      List torrents
- `remove-duplicates`  Remove duplicates obsolete torrents
- `send-metrics`       Send metrics to InfluxDB

#### Global command options
- `--config` - set path to config file
- `--host` - set transmission host
- `--dry-run` - don't change any data
- `-v|vv|vvv` - more verbose output


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

#### Transmission
You need to enable remote access in Transmission
and add host, port, username, password if it not defaults.
You can change it in `~/.transmission-cli.yml`.
You can pass host though --host=host option.

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
59 * * * * transmission-cli remove-duplicates --host=localhost
59 * * * * transmission-cli remove-duplicates --host=wrtnsq
0  * * * * transmission-cli send-metrics --host=localhost
0  * * * * transmission-cli send-metrics --host=wrtnsq
1  2 * * * transmission-cli download-weburg --dest=/Volumes/media/_planeta/_torrents
```


# Check code style
```
 ./vendor/bin/phpcs --standard=psr2 ./src ./tests
 ./vendor/bin/phpmd src/ text codesize,controversial,design,naming,unusedcode
```

# TODO:
- [ ] test phar
- [ ] 80% coverage
- [ ] docs
- [ ] packagist
- [ ] track deleted torrents
- [ ] more strict variable naming
- [ ] move all weburg code to WeburgClient