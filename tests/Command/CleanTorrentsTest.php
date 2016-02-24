<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class CleanTorrentsTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('clean-torrents');
        parent::setUp();
    }

    /*public function testWithoutOptions()
    {
        $this->executeCommand();
    }*/

    public function testWithoutBlacklist()
    {
        $this->executeCommand();
    }

    public function testWithBlacklist()
    {
        $blacklist_file = tempnam(sys_get_temp_dir(), 'blacklist');
        file_put_contents($blacklist_file, 'name2.ext');
        $this->executeCommand(['--blacklist' => $blacklist_file]);
        unlink($blacklist_file);
    }

    public function testDryRun()
    {
        $blacklist_file = tempnam(sys_get_temp_dir(), 'blacklist');
        file_put_contents($blacklist_file, 'name2.ext');
        $this->executeCommand(['--blacklist' => $blacklist_file, '--dry-run' => true]);
        unlink($blacklist_file);
    }
}
