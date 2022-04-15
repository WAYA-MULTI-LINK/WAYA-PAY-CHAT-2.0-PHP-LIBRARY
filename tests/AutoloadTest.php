<?php
namespace Pils36\WayapayTests;

class AutoloadTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoload()
    {
        $wayapay_autoloader = require(__DIR__ . '/../src/autoload.php');
        $wayapay_autoloader('Pils36\\Wayapay\\Routes\\Transaction');
    }
}