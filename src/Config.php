<?php

namespace Popstas\Transmission\Console;

use Symfony\Component\Console\Input\InputInterface;
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
        
        'weburg-series-list' => [],
        'weburg-series-max-age' => 1,
        'weburg-series-allowed-misses' => 1,
        'weburg-request-delay' => 2
    );

    private $config;
    private $configFile;

    public function __construct($configFile = null)
    {
        $this->config = self::$defaultConfig;

        $this->configFile = $configFile;
        if (!isset($configFile)) {
            $this->configFile = self::getHomeDir() . '/.transmission-cli.yml';
        }
    }

    public function loadConfigFile()
    {
        if (!file_exists($this->configFile)) {
            throw new \RuntimeException('Config file not found: ' . $this->configFile);
        }
        $yml = Yaml::parse(file_get_contents($this->configFile));
        if (!is_array($yml)) {
            throw new \RuntimeException('Config file corrupted: ' . $this->configFile);
        }
        $this->config = $yml + $this->config;
    }

    public function saveConfigFile()
    {
        $configRaw = Yaml::dump($this->config, 2);
        file_put_contents($this->configFile, $configRaw);
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

    public function overrideConfig(InputInterface $input, $optionName, $configName = null)
    {
        if (!isset($configName)) {
            $configName = $optionName;
        }

        $optionValue = $input->hasOption($optionName) ? $input->getOption($optionName) : null;
        if (isset($optionValue)) {
            $this->set($configName, $optionValue);
        }

        return $this->get($configName);
    }

    public static function getHomeDir()
    {
        // environment variable 'HOME' isn't set on Windows and generates a Notice.
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
