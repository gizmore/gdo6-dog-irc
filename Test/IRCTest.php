<?php
namespace GDO\DogIRC\Test;

use function PHPUnit\Framework\assertMatchesRegularExpression;
use GDO\DogIRC\IRCTestCase;

final class IRCTest extends IRCTestCase
{
    public function testServerAdd()
    {
        $response = $this->bashCommand('irc.join_network');
        if (GWF_CONSOLE_VERBOSE)
        {
            echo $response . "\n";
        }
        assertMatchesRegularExpression('/usage:/is', $response, 'Check if usage is shown on error');
        
        $response = $this->bashCommand('irc.join_network irc://irc.giz.org');
        if (GWF_CONSOLE_VERBOSE)
        {
            echo $response . "\n";
        }
        assertMatchesRegularExpression('/ join /is', $response, 'Check if join response comes.');
    }
    
}
