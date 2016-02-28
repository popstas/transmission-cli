<?php

namespace Popstas\Transmission\Console;

use InvalidArgumentException;
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
        'weburg-series-max-age' => 1
    );

    private $config;
    private $configFile;

    public function __construct($configFile = null)
    {
        $this->config = static::$defaultConfig;

        if (!isset($configFile)) {
            $configFile = self::getHomeDir() . '/.transmission-cli.yml';
            if (!file_exists($configFile)) {
                $this->saveConfigFile($configFile);
            }
        }
        $this->configFile = $configFile;
        if ($configFile) {
            $this->loadConfigFile($configFile);
        }
    }

    public function loadConfigFile($configFile)
    {
        if (!file_exists($configFile)) {
            throw new InvalidArgumentException('Config file not found: ' . $configFile);
        }
        $yaml = Yaml::parse(file_get_contents($configFile));
        $this->config = $yaml + $this->config;
    }

    public function saveConfigFile($configFile = null)
    {
        if (!isset($configFile)) {
            $configFile = $this->configFile;
        }
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
