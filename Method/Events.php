<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\DOG_Command;
use GDO\Dog\DOG_User;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\DogIRC\IRCLib;
use GDO\Dog\Dog;
use GDO\Dog\DOG_Message;
use GDO\DogIRC\Connector\IRC;
use GDO\User\GDO_UserPermission;

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
    }
    
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
        /** @var IRC $connector  **/
        $connector = $server->getConnector();
        switch ($text)
        {
            case 'VERSION':
                $connector->sendCTCP($user->getName(), "GDO6 - DOG BOT v6.11 - IRC CONNECTOR v6.11");
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
    
    /**
     * Online list
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
        
        foreach (explode(" ", $onlineList) as $userName)
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
    
}
