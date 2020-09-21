<?php
namespace GDO\DogIRC;

use GDO\Core\GDO;
use GDO\Dog\GDT_Server;
use GDO\DB\GDT_String;
use GDO\Dog\DOG_Server;

final class DOG_IRCServerSettings extends GDO
{
    public function gdoColumns()
    {
        return array(
            GDT_Server::make('irc_server_id')->primary()->notNull(),
            GDT_String::make('irc_server_software'),
        );
    }
    
    public static function getOrCreate(DOG_Server $server)
    {
        if (!($settings = self::getById($server->getID())))
        {
            $settings = self::blank(['irc_server_id' => $server->getID()])->insert();
        }
        return $settings;
    }

}
