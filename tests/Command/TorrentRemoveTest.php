<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class TorrentRemoveTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-remove');
        parent::setUp();
    }

    public function testRemoveOne()
    {
        $this->executeCommand(['torrent-ids' => [1], '-y' => true]);
        $this->assertRegExp('/removed/', $this->getDisplay());
    }

    public function testRemoveConfirmation()
    {
        $command = $this->getCommand();

        $question = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $question->expects($this->at(0))
            ->method('ask')
            ->will($this->returnValue(true));

        $command->getHelperSet()->set($question, 'question');

        $result = $this->executeCommand(['torrent-ids' => [1]]);
        $this->assertEquals(0, $result);
        $this->assertRegExp('/Torrents removed/', $this->getDisplay());


        // not confirmed
        $question = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $question->expects($this->at(0))
            ->method('ask')
            ->will($this->returnValue(false));

        $command->getHelperSet()->set($question, 'question');

        $result = $this->executeCommand(['torrent-ids' => [1]]);
        $this->assertEquals(1, $result);
        $this->assertRegExp('/Aborting/', $this->getDisplay());
    }

    public function testRemoveSoft()
    {
        $result = $this->executeCommand(['torrent-ids' => [1], '-y' => true, '--soft' => true]);
        $this->assertEquals(0, $result);
        $this->assertRegExp('/Data don\'t removed/', $this->getDisplay());
    }

    public function testRemoveSeveral()
    {
        $this->executeCommand(['torrent-ids' => [1, 2], '-y' => true]);
        $this->assertRegExp('/removed/', $this->getDisplay());
    }

    public function testRemoveSeveralNotExist()
    {
        $result = $this->executeCommand(['torrent-ids' => [1, 2, 999], '-y' => true]);
        $this->assertEquals(1, $result);
        $this->assertRegExp('/not exists/', $this->getDisplay());
    }
}
