<?php
declare(strict_types=1);
namespace GDO\DogIRC;

use GDO\Dog\DOG_Command;
use GDO\Dog\DOG_Connector;
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

	protected function getConnectors(): array { return ['IRC']; }

	public function getConnector(DOG_Message $message): DOG_Connector
	{
		return $message->server->getConnector();
	}

}
