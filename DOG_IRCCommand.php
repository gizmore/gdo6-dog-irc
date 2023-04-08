<?php
namespace GDO\DogIRC;

use GDO\Dog\DOG_Command;
use GDO\Dog\DOG_Message;
use GDO\DogIRC\Connector\IRC;

/**
 * IRC Commands have only one allowed connector and a predefined group.
 *
 * @author gizmore
 */
abstract class DOG_IRCCommand extends DOG_Command
{

	public function getCLITriggerGroup(): string
	{
		return 'irc';
	}

	protected function getConnectors() { return ['IRC']; }

	/**
	 * @param DOG_Message $message
	 *
	 * @return IRC
	 */
	public function getConnector(DOG_Message $message) { return $message->server->getConnector(); }

}
