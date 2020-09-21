<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\DOG_Message;
use GDO\DogIRC\DOG_IRCCommand;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\Dog\DOG_User;


/**
 * Make the bot join a channel.
 * @author gizmore
 */
final class Part extends DOG_IRCCommand
{
    public $trigger = 'part_channel';
    
    public function getPermission() { return Dog::OPERATOR; }
    
    public function isPrivateMethod() { return false; }
    
    public function dogExecute(DOG_Message $message)
    {
        /** @var \GDO\DogIRC\Connector\IRC $connector **/
        $connector = $message->server->getConnector();
        $roomName = $message->room->getName();
        $command = "PART {$roomName}";
        $message->rply('msg_part_irc_channel', [$roomName]);
        $connector->send($command);
        
        $message->server->removeRoom($message->room);
        
        ### Set autojoin flag
        $join = Join::byTrigger('join_channel');
        $join->setConfigValueRoom($message->room, 'autojoin', false);
    }
    
    public function irc_PART(DOG_Server $server, DOG_User $user, $roomName)
    {
        $room = DOG_Room::getOrCreate($server, $roomName);
        $server->removeRoom($room);
        
    }
    
}
