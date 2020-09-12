<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\DOG_Command;
use GDO\Dog\DOG_User;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;

final class Events extends DOG_Command
{
    public $priority = 1;
    
    public function irc_ERROR(DOG_Server $server, $raw)
    {
        $server->getConnector()->disconnect($raw);
    }
    
    public function irc_PING(DOG_Server $server, $ping)
    {
        /** @var \GDO\DogIRC\Connector\IRC $connector **/
        $connector = $server->getConnector();
        $connector->send("PONG {$ping}");
    }
    
    public function irc_NOTICE(DOG_Server $server, DOG_User $user, $to, $text)
    {
        $room = DOG_Room::getByName($server, $to);
    }
    
    public function irc_PRIVMSG(DOG_Server $server, DOG_User $user, $to, $text)
    {
        $room = DOG_Room::getByName($server, $to);
        
    }
    
    public function irc_001(DOG_Server $server, DOG_User $user, $to, $text)
    {
        if ($password = $server->getPassword())
        {
            $this->identify($username, $password);
        }
    }
    
}
