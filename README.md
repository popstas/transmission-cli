transmission-cli is console php application for automate torrent download/upload from weburg.net

Based on [symfony/console](http://symfony.com/doc/current/components/console/index.html),
[martial/transmission-api](https://github.com/MartialGeek/transmission-api),
[leominov/weburg-torrent-grabber](https://github.com/leominov/weburg-torrent-grabber).

# Features
- send metrics to InfluxDB, torrent monitoring trends with Grafana
- download popular (rating and comment based) movies from http://weburg.net
- delete not popular uploads from transmission
- working with multiple transmission instances

![Screenshot](doc/img/grafana.png?raw=true)


# Available commands:
- `_completion`        BASH completion hook.
- `clean-torrents`     Cleans torrents
- `download-weburg`    Download torrents from weburg.net
- `help`               Displays help for a command
- `list`               Lists commands
- `list-torrents`      List torrents
- `remove-duplicates`  Remove duplicates obsolete torrents
- `send-metrics`       Send metrics to InfluxDB

# Install

```
git clone https://github.com/popstas/transmission-cli
cd transmission-cli
composer install
ln -s "$PWD"/bin/transmission-cli /usr/local/bin/transmission-cli
```

#### Transmission
You need to enable remote access in Transmission
and add host, port, username, password if it not defaults.


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


# TODO:
- [ ] config file
- [ ] phpunit
- [ ] travisCI
- [ ] packagist
- [ ] docs