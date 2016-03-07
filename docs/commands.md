## Get torrents stats from InfluxDB

Command `stats-get` almost same as `torrent-list`, but it use InfluxDB:
```
transmission-cli stats-get [--name='name'] [--age='age'] [profit='>0'] [--days=7] [--sort=1] [--limit=10] [--rm]
```

You can also use `--name`, `--age`, `--sort`, `--limit`, plus `--profit` and `--days` options.

Profit = uploaded for period / torrent size. Profit metric more precise than uploaded ever value.

Show 10 worst torrents for last week:
```
transmission-cli stats-get --days 7 --profit '=0' --limit 10
```

Show stats of last added torrents sorted by profit:
```
transmission-cli stats-get --days 1 --age '<2' --sort='-7'
```


## Remove torrents

You can use command `stats-get` with `--rm` option to remove filtered unpopular torrents:
```
transmission-cli stats-get --days 7 --profit '=0' --rm
```

With `--rm` option you can use all options of `torrent-remove` command: `--soft`, `--dry-run`, `-y`.  

Without `-y` option command ask your confirmation for remove torrents.  

If you don't want to remove all filtered torrents, you can save ids of torrents and call `torrent-remove` manually.


## Send statistic to InfluxDB

Command should called every hour or more, possible every day, but it will less precise.

Command assume that all torrents have unique names.

You should to configure InfluxDB to use this functional.


## Add torrents

By default, Transmission may to freeze if you add several torrents at same time.
Therefore, preferred way to add torrents - with `torrent-add`.
After each add file command sleeps for 10 seconds for give time to freeze Transmission.
After that command waits for Transmission answer and add next file, etc.

```
transmission-cli torrent-add file|url [file2] [fileX]
```


## List torrents

You can list torrents from transmission with `torrent-list` command:
```
transmission-cli torrent-list [--sort=column_number] [--name='name'] [--age='>0'] [--limit=10]
```

**List columns:**

- Name
- Id - need for `torrent-remove` command
- Age - days from torrent done date
- Size - size of downloaded data
- Uploaded - size of uploaded data
- Per day - average uploaded GB per day


#### Sorting list

You can sort torrents by `Per day` column and estimate unpopular torrents:
```
transmission-cli torrent-list --sort=6
```

For reverse sort ordering, add `-` to column number:
```
transmission-cli torrent-list --sort=-6
```


#### Filtering torrent list

**By age:**
```
transmission-cli torrent-list --age '>10'
transmission-cli torrent-list --age '< 20'
transmission-cli torrent-list --age '>0 <5'
```

**By name:**
You can use simple regular expression except `.` and `/` symbols.

Filter FullHD series:
```
transmission-cli torrent-list --name 'season*1080*'
```

Filter all mkv and avi:
```
transmission-cli torrent-list --name '.mkv|.avi'
```

#### Limiting torrent list

Output 10 worst torrents:
```
transmission-cli torrent-list --sort=6 --limit 10
```


## Remove torrents

Torrents removes only by id, you can see torrent id in `torrent-list` output.

By default torrents removes with data! Data deletes to trash on Mac OS and totally removes on Windows!

Remove one torrent:
```
transmission-cli torrent-remove 1
```

Remove several torrents:
```
transmission-cli torrent-remove 1 2
```

Remove only torrent from transmission, not delete data:
```
transmission-cli torrent-remove 1 --soft
```

For select not popular torrents and remove it, see `transmission-cli stats-get --help`


## Remove series torrent duplicates

While download series every time you add torrent with same name to Transmission.
It corrupts `stats-send` command, therefore we need to remove old torrents. This command doing this.

Just call:
```
transmission-cli torrent-remove-duplicates
```

Command removes all torrents with same name and same download directory from Transmission.
**It not removes any files!**


## Download torrents from Weburg.net

You can automatically download popular torrents from http://weburg.net/movies/new out of the box, use command:
```
transmission-cli weburg-download --download-torrents-dir=/path/to/torrents/directory
```

or define `download-torrents-dir` in config and just:
```
transmission-cli weburg-download
```

You can automatically download new series, for add series to tracked list see `transmission-cli weburg-series-add`.
It is pretty simple:
```
transmission-cli weburg-series-add http://weburg.net/series/info/12345
```

After that command `weburg-download` also will download series from list for last day.
If you don't want to download popular torrents, but only new series, use command:
```
transmission-cli weburg-download --download-torrents-dir=/path/to/torrents/directory --series
```


## Add series to download list

You can automatically download new series. To do this, you should add series to download list:
```
transmission-cli weburg-series-add http://weburg.net/series/info/12345
```

After that command `weburg-download` also will download series from list for last day.
If you don't want to download popular torrents, but only new series, use command:
```
transmission-cli weburg-download --series
```


