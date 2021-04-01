<?php
namespace GDO\DogIRC\Test;

use function PHPUnit\Framework\assertMatchesRegularExpression;
use GDO\DogIRC\IRCTestCase;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertIsObject;

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
        
        $response = $this->bashCommand('irc.join_network irc://localhost:6667');
        if (GWF_CONSOLE_VERBOSE)
        {
            echo $response . "\n";
        }
        assertMatchesRegularExpression('/ join /is', $response, 'Check if join response comes.');
        
        $response = $this->ircResponse(500000);
        assertTrue(stripos($response, ' auth')!==false, 'check if connection to IRC is being established');
    
        sleep(3);
        $response = $this->ircResponse(100000);
        assertTrue(stripos($response, ' invisible ')!==false, 'check if connection to IRC is established');
    }
    
    public function testJoin()
    {
        $this->user($this->userGizmore2());
        $response = $this->ircPrivmsg('irc.join #dog');
        assertTrue(stripos($response, ' join ')!==false, 'check if join command works.');
        $room = $this->getDogRoom();
        assertIsObject($room, 'Test if room is created.');
        assertTrue($room->isPersisted(), 'Test if room is persisted.');
    }
    
}
