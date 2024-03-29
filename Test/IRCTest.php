<?php
namespace GDO\DogIRC\Test;

use GDO\Dog\Dog;
use GDO\Dog\DOG_Server;
use function PHPUnit\Framework\assertIsObject;
use function PHPUnit\Framework\assertMatchesRegularExpression;
use function PHPUnit\Framework\assertTrue;

final class IRCTest extends IRCTestCase
{

	public function testServerAdd()
	{
		$response = $this->bashCommand('irc.connect');
		assertMatchesRegularExpression('/usage:/is', $response, 'Check if usage is shown on error');

		$response = $this->bashCommand('irc.connect irc://irc.giz.org:6667');
		assertMatchesRegularExpression('/ join /is', $response, 'Check if join response comes.');

		$response = $this->ircResponse();
		assertTrue(stripos($response, ' auth') !== false, 'check if connection to IRC is being established');
		sleep(2);
		$response .= $this->ircResponse();
		assertTrue(stripos($response, 'established') !== false, 'check if connection to IRC is establishing');

		$response = $this->ircPrivmsg('ping');
		assertTrue(stripos($response, 'PONG') !== false, 'check if connection to IRC is established');
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
		assertTrue(stripos($response, ' join ') !== false, 'check if join command works.');
		$room = $this->getDogRoom();
		assertIsObject($room, 'Test if room is created.');
		assertTrue($room->isPersisted(), 'Test if room is persisted.');
	}

}
