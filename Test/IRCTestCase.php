<?php
namespace GDO\DogIRC\Test;

use GDO\Dog\Dog;
use GDO\Dog\DOG_Message;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\DOG_User;
use GDO\Dog\Test\DogTestCase;
use GDO\DogIRC\Connector\IRC;
use GDO\Tests\GDT_MethodTest;
use GDO\User\GDO_User;
use GDO\User\GDO_UserPermission;
use GDO\User\GDT_UserType;

class IRCTestCase extends DogTestCase
{

	public function setUp(): void
	{
		parent::setUp();
		$server = DOG_Server::getBy('serv_connector', 'IRC');
		if ($server)
		{
			DOG_User::getOrCreateUser($server, 'gizmore');
			$this->restoreUserPermissions($this->userGizmore2());
			$this->user($this->userGizmore2());
		}
	}

	protected function restoreUserPermissions(GDO_User $user): void
	{
		if (count(GDT_MethodTest::$TEST_USERS))
		{
			$g2 = GDO_User::getByName('gizmore{2}');
			if ($g2)
			{
				if ($user->getID() === $g2->getID())
				{
					GDO_UserPermission::grant($user, 'admin');
					GDO_UserPermission::grant($user, 'staff');
					GDO_UserPermission::grant($user, 'cronjob');
					GDO_UserPermission::grant($user, Dog::VOICE);
					GDO_UserPermission::grant($user, Dog::HALFOP);
					GDO_UserPermission::grant($user, Dog::OPERATOR);
					$user->changedPermissions();
				}
			}
		}
	}

	protected function userGizmore2(): GDO_User
	{
		return GDO_User::findBy('user_name', 'gizmore{2}');
	}

	public function createUser(string $username, DOG_Server $server = null): DOG_User
	{
		$server = $server ?: $this->getServer();

		$sid = $server->getID();
		$longUsername = "{$username}\{{$sid}\}";
		if (!($user = GDO_User::getBy('user_name', $longUsername)))
		{
			$user = GDO_User::blank([
				'user_name' => $longUsername,
				'user_type' => GDT_UserType::MEMBER,
			])->insert();
		}

		if (
			!($doguser = DOG_User::table()->select()->
			where('doguser_server=' . $sid)->
			where('doguser_name=' . quote($username))->
			first()->exec()->fetchObject())
		)
		{
			$doguser = DOG_User::blank([
				'doguser_name' => $username,
				'doguser_server' => $sid,
				'doguser_user' => $user->getID(),
			])->insert();
		}

		$server->addUser($doguser);

		if ($room = $this->getDogRoom())
		{
			$room->addUser($doguser);
		}

		return $doguser;
	}

	protected function getServer(): DOG_Server
	{
		$server = DOG_Server::getBy('serv_connector', 'IRC');
		return $server ?: parent::getServer();
	}

	protected function getDogRoom(): ?DOG_Room
	{
		return DOG_Room::getByName($this->getServer(), '#dog');
	}

	public function ircPrivmsgRoom($text, $usleep = 500000)
	{
		$room = $this->getDogRoom();
		$text = $room->getTrigger() . $text;
		return $this->ircPrivmsg($text, $room, $usleep);
	}

	public function ircPrivmsg($text, DOG_Room $room = null, $usleep = 500000)
	{
		$server = $this->getServer();
		$message = DOG_Message::make()->
		user($this->doguser)->server($server)->
		room($room)->text($text);
		Dog::instance()->event('dog_message', $message);
		return $this->ircResponse($usleep);
	}

	public function ircResponse(int $usleep = 250000): string
	{
		$mode = 1;
		$response = '';
//		try
//		{
		usleep($usleep); # 250ms
		while ($mode)
		{
			Dog::instance()->mainloopStep();
			$r = ob_get_contents();
			$response .= $r;
			ob_flush();
			if (!$r)
			{
				if ($mode == 2)
				{
					break;
				}
				$mode = 2;
			}
			usleep(250000);
		}
		return $response;
//		}
//		catch (Throwable $ex)
//		{
//			throw $ex;
//		}
	}

	protected function getConnector(): IRC
	{
		return $this->getServer()->getConnector();
	}

}
