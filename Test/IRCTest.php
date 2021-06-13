<?php
namespace GDO\DogIRC\Test;

use function PHPUnit\Framework\assertMatchesRegularExpression;
use GDO\DogIRC\IRCTestCase;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertIsObject;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;

final class IRCTest extends IRCTestCase
{
    public function testServerAdd()
    {
        $response = $this->bashCommand('irc.join_network');
        assertMatchesRegularExpression('/usage:/is', $response, 'Check if usage is shown on error');
        
        $response = $this->bashCommand('irc.join_network irc://irc.giz.org:6667');
        assertMatchesRegularExpression('/ join /is', $response, 'Check if join response comes.');
        
        $response = $this->ircResponse(1000000);
        assertTrue(stripos($response, ' auth')!==false, 'check if connection to IRC is being established');
        sleep(3);
        $response .= $this->ircResponse(1000000);
        assertTrue(stripos($response, ' invisible ')!==false, 'check if connection to IRC is establishing');

        $response = $this->ircPrivmsg('ping');
        assertTrue(stripos($response, 'PONG')!==false, 'check if connection to IRC is established');
    }
    
    public function testServerCache()
    {
        $server1 = DOG_Server::getBy('serv_connector', 'IRC');
        $server2 = Dog::instance()->servers[2];
        assertTrue($server1 === $server2, 'Test if server cache is working.');
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
