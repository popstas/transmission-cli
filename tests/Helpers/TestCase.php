<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    public $expectedTorrentList = [
        ['downloadDir' => '/', 'id' => 1, 'name' => 'name.ext', 'totalSize' => 1, 'uploadedEver' => 3],
        ['downloadDir' => '/', 'id' => 2, 'name' => 'name.ext', 'totalSize' => 2, 'uploadedEver' => 4],
        ['downloadDir' => '/', 'id' => 3, 'name' => 'name2.ext', 'totalSize' => 3, 'uploadedEver' => 5],
        ['downloadDir' => '/dir2', 'id' => 4, 'name' => 'name.ext', 'totalSize' => 1, 'uploadedEver' => 5]
    ];

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function rmdir($path)
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }

    public function getTestPage($name)
    {
        $body = file_get_contents('tests/data/' . $name . '.html');
        return $body;
    }
}
