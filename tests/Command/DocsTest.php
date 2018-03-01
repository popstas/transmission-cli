<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class DocsTest extends CommandTestCase
{

    public function setUp()
    {
        $this->setCommandName('_docs');
        parent::setUp();
    }

    public function testDocs()
    {
        $result = $this->executeCommand();
        $this->assertEquals($result, 0);
    }
}
