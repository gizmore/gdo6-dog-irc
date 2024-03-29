<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\Dog;
use GDO\Dog\DOG_Message;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\DOG_User;
use GDO\DogIRC\Connector\IRC;
use GDO\DogIRC\DOG_IRCCommand;


/**
 * Make the bot join a channel.
 *
 * @author gizmore
 */
final class Part extends DOG_IRCCommand
{

	public function getCLITrigger(): string
	{
		return 'irc.part';
	}

	public function getPermission(): ?string { return Dog::OPERATOR; }

	protected function isPrivateMethod(): bool { return false; }

	public function dogExecute(DOG_Message $message)
	{
		/** @var IRC $connector * */
		$connector = $message->server->getConnector();
		$roomName = $message->room->getName();
		$command = "PART {$roomName}";
		$message->rply('msg_part_irc_channel', [$roomName]);
		$connector->send($command);

		$message->server->removeRoom($message->room);

		### Set autojoin flag
		$join = Join::byTrigger('irc.join');
		$join->setConfigValueRoom($message->room, 'autojoin', false);
	}

	public function irc_PART(DOG_Server $server, DOG_User $user, $roomName)
	{
		$room = DOG_Room::getOrCreate($server, $roomName);
		$server->removeRoom($room);
	}

}
