<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanTorrentsTest extends CommandTestCase
{
    /**
     * @var string $blacklist_file
     */
    private $blacklist_file;

    public function setUp()
    {
        $this->setCommandName('clean-torrents');
        parent::setUp();

        $this->blacklist_file = tempnam(sys_get_temp_dir(), 'blacklist');
        file_put_contents($this->blacklist_file, 'name2.ext');
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /*public function testWithoutOptions()
    {
        $this->executeCommand();
    }*/

    public function testWithoutBlacklist()
    {
        unlink($this->blacklist_file);
        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
    }

    public function testWithBlacklist()
    {
        $this->executeCommand(['--blacklist' => $this->blacklist_file]);
    }

    public function testDryRun()
    {
        $logger = $this->getMock('\Psr\Log\LoggerInterface');
        $logger->expects($this->never())->method('critical');
        $this->app->setLogger($logger);

        $this->executeCommand(['--blacklist' => $this->blacklist_file, '--dry-run' => true]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }
}
