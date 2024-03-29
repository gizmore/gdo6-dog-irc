<?php
namespace GDO\DogIRC\Method;

use GDO\Core\Module_Core;
use GDO\Dog\Dog;
use GDO\Dog\DOG_Command;
use GDO\Dog\DOG_Message;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\DOG_User;
use GDO\DogIRC\Connector\IRC;
use GDO\DogIRC\DOG_IRCServerSettings;
use GDO\DogIRC\IRCLib;
use GDO\User\GDO_UserPermission;
use GDO\Util\Regex;

/**
 * IRC Event handler.
 *
 * @author gizmore
 */
final class Events extends DOG_Command
{

	public int $priority = 1;

	public function isWebMethod() { return false; }

	public function isHiddenMethod(): bool { return true; }

	protected function isRoomMethod(): bool { return false; }

	protected function isPrivateMethod(): bool { return false; }

	public function irc_ERROR(DOG_Server $server, $raw)
	{
		$this->getConnector($server)->disconnected();
	}

	/**
	 * @param DOG_Server $server
	 *
	 * @return IRC
	 */
	public function getConnector(DOG_Server $server)
	{
		return $server->getConnector();
	}

	public function irc_PING(DOG_Server $server, $ping)
	{
		/** @var IRC $connector * */
		$connector = $server->getConnector();
		$connector->send("PONG {$ping}");
	}

	public function irc_NOTICE(DOG_Server $server, DOG_User $user, $to, $text) {}

	public function irc_PRIVMSG(DOG_Server $server, DOG_User $user, $to, $text)
	{
		// CTCP check
		if ($text[0] === IRCLib::CTCP)
		{
			Dog::instance()->event('irc_CTCP', $server, $user, trim($text, IRCLib::CTCP));
			return;
		}

		// Proxy to main event
		$room = $server->getRoomByName($to);
		$message = new DOG_Message();
		$message->room($room)->text($text)->server($server)->user($user);
		Dog::instance()->event('dog_message', $message);
	}

	public function irc_CTCP(DOG_Server $server, DOG_User $user, $text)
	{
		/** @var IRC $connector * */
		$connector = $server->getConnector();
		switch ($text)
		{
			case 'VERSION':
				$connector->sendCTCP($user->getName(), 'GDO6 - DOG BOT ' . Module_Core::GDO_REVISION . ' - IRC CONNECTOR v6.10.6');
				break;
		}
	}

	public function irc_JOIN(DOG_Server $server, DOG_User $user, $roomName)
	{
		$room = DOG_Room::getOrCreate($server, $roomName);
		$server->addRoom($room);
		$room->addUser($user);
		Dog::instance()->event('dog_join', $server, $user, $room);
	}

	public function irc_KICK(DOG_Server $server, DOG_User $user, $roomName, $userName)
	{
		$room = DOG_Room::getOrCreate($server, $roomName);
		$userKicked = DOG_User::getOrCreateUser($server, $userName);
		$server->addRoom($room);
		$server->addUser($userKicked);
		$room->removeUser($userKicked);
		if ($userKicked === $server->getDog())
		{
			$server->removeRoom($room);
			Dog::instance()->event('dog_kicked', $server, $user, $room);
		}
		Dog::instance()->event('dog_kick', $server, $user, $room);
	}

	public function irc_PART(DOG_Server $server, DOG_User $user, $roomName)
	{
		$room = DOG_Room::getOrCreate($server, $roomName);
		$server->addRoom($room);
		if ($user === $server->getDog())
		{
			$server->removeRoom($room);
			Dog::instance()->event('dog_parted', $server, $user, $room);
		}
		Dog::instance()->event('dog_part', $server, $user, $room);
	}

	/**
	 * Welcome message.
	 *
	 * @param DOG_Server $server
	 * @param DOG_User $user
	 * @param string $me
	 * @param string $welcome
	 */
	public function irc_001(DOG_Server $server, DOG_User $user, $me, $welcome)
	{
		$this->getConnector($server)->nickname = $me;
		$user->saveVar('doguser_service', '1');
		Dog::instance()->event('dog_server_authenticated', $server);
	}

	###############
	### Numeric ###
	###############

	public function irc_002(DOG_Server $server, DOG_User $user, $me, $version)
	{
		$settings = $this->getSettings($server);
		$settings->saveVar('irc_server_software', Regex::firstMatch('/running version (.*)$/iuD', $version));
	}

	/**
	 * @param DOG_Server $server
	 *
	 * @return DOG_IRCServerSettings
	 */
	public function getSettings(DOG_Server $server)
	{
		return $this->getConnector($server)->getSettings();
	}

	/**
	 * Online list
	 *
	 * @param DOG_Server $server
	 * @param DOG_User $user
	 * @param string $me
	 * @param string $equal
	 * @param string $roomName
	 * @param string $onlineList
	 */
	public function irc_353(DOG_Server $server, DOG_User $user, $me, $equal, $roomName, $onlineList)
	{
		$room = DOG_Room::getOrCreate($server, $roomName);

		foreach (explode(' ', $onlineList) as $userName)
		{
			$permission = IRCLib::permissionFromUsername($userName);
			$userName = IRCLib::trimmedUsername($userName);
			$user = DOG_User::getOrCreateUser($server, $userName);
			$room->addUser($user);
			if ($permission)
			{
				if (!$user->getGDOUser()->hasPermission($permission))
				{
					GDO_UserPermission::grant($user->getGDOUser(), $permission);
				}
			}
		}
	}

	/**
	 * Nick in use.
	 *
	 * @param DOG_Server $server
	 * @param DOG_User $user
	 * @param string $star
	 * @param string $me
	 * @param string $text
	 */
	public function irc_433(DOG_Server $server, DOG_User $user, $star, $me, $text)
	{
		$this->getConnector($server)->send('NICK ' . $server->nextUsername());
	}

}
