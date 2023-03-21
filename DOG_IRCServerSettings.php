<?php
namespace GDO\DogIRC;

use GDO\Core\GDO;
use GDO\Core\GDT_String;
use GDO\Dog\DOG_Server;
use GDO\Dog\GDT_Server;

final class DOG_IRCServerSettings extends GDO
{

	public static function getOrCreate(DOG_Server $server)
	{
		if (!($settings = self::getById($server->getID())))
		{
			$settings = self::blank(['irc_server_id' => $server->getID()])->insert();
		}
		return $settings;
	}

	public function gdoColumns(): array
	{
		return [
			GDT_Server::make('irc_server_id')->primary()->notNull(),
			GDT_String::make('irc_server_software'),
		];
	}

}
