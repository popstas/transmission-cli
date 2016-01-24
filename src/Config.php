<?php

namespace Popstas\Transmission\Console;

class Config
{
    public static $defaultConfig = array(
        'transmission-host'     => 'localhost',
        'transmission-port'     => 9091,
        'transmission-user'     => '',
        'transmission-password' => '',

        'influxdb-host'     => 'localhost',
        'influxdb-port'     => 8086,
        'influxdb-database' => 'transmission',
        'influxdb-user'     => '',
        'influxdb-password' => '',

        'download-torrents-dir'  => '',
        'download-imdb-min'      => 8.0,
        'download-kinopoisk-min' => 8.0,
        'download-comments-min'  => 10,
        'download-votes-min'  => 25,
    );

    private $config;

    public function __construct()
    {
        // load defaults
        $this->config = static::$defaultConfig;
    }

    public function get($key)
    {
        if (!isset($this->config[$key])) {
            return null;
        }
        return $this->config[$key];
    }

    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }
}
