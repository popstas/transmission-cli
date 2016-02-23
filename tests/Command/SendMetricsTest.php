<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class SendMetricsTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('send-metrics');
        parent::setUp();
    }

    /*public function testWithoutOptions()
    {
        $this->executeCommand();
    }*/

    /**
     * @expectedException \GuzzleHttp\Exception\ConnectException;
     */
    /*public function testInfluxDbConnectionError()
    {
        $config = new Config();
        $config->set('influxdb-host', 'null');
        $this->app->setConfig($config);

        $this->executeCommand();
    }*/

    /*public function testDryRun()
    {
        $this->executeCommand(['--dry-run' => true]);
    }*/

    public function testObsoleteTorrentsExists()
    {
        $this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));
        $this->executeCommand();
        $this->assertRegExp('/Found obsolete torrents/', $this->getDisplay());
    }
}
