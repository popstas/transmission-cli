<?php

namespace Popstas\Transmission\Console;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    private static $defaultConfig = array(
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
        'download-imdb-min'      => '8.0',
        'download-kinopoisk-min' => '8.0',
        'download-comments-min'  => 10,
        'download-votes-min'     => 25,
    );

    private $config;

    public function __construct()
    {
        // load defaults
        $this->config = static::$defaultConfig;
    }

    public function loadConfigFile($configFile = null)
    {
        if (!isset($configFile)) {
            $configFile = $this->getHomeDir() . '/.transmission-cli.yml';
            if (!file_exists($configFile)) {
                $this->saveConfigFile($configFile);
            }
        } else {
            if (!file_exists($configFile)) {
                throw new InvalidArgumentException('Config file not found: ' . $configFile);
            }
        }
        $yaml = Yaml::parse(file_get_contents($configFile));
        $this->config = $yaml + $this->config;
    }

    public function saveConfigFile($configFile)
    {
        $config_raw = Yaml::dump($this->config, 2);
        file_put_contents($configFile, $config_raw);
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

    private function getHomeDir()
    {
        // Cannot use $_SERVER superglobal since that's empty during UnitUnishTestCase
        // getenv('HOME') isn't set on Windows and generates a Notice.
        $home = getenv('HOME');
        if (!empty($home)) {
            // home should never end with a trailing slash.
            $home = rtrim($home, '/');
        } elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            // home on windows
            $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
            // If HOMEPATH is a root directory the path can end with a slash. Make sure
            // that doesn't happen.
            $home = rtrim($home, '\\/');
        }
        return empty($home) ? null : $home;
    }
}
