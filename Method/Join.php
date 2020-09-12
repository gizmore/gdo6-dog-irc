<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\DOG_Message;
use GDO\DogIRC\DOG_IRCCommand;
use GDO\DB\GDT_String;
use GDO\Core\GDT_Secret;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;

/**
 * Make the bot join a channel.
 * @author gizmore
 */
final class Join extends DOG_IRCCommand
{
    private $passwords = array();
    
    public function getTrigger() { return 'join_channel'; }
    public function getPermission() { return 'halfop'; }
    
    public function gdoParameters()
    {
        return array(
            GDT_String::make('channel')->notNull(),
            GDT_Secret::make('password'),
        );
    }
    
    public function dogExecute(DOG_Message $message, $channelName, $password)
    {
        /** @var \GDO\DogIRC\Connector\IRC $connector **/
        $connector = $message->server->getConnector();
        $command = "JOIN $channelName";
        $command .= $password ? " $password" : '';
        $message->rply('msg_join_irc_channel', [$channelName]);
        $connector->send($command);
        $this->passwords[$channelName] = $password;
    }
    
    public function irc_JOIN(DOG_Server $server, $channelName, $username)
    {
        if (isset($this->passwords[$channelName]))
        {
            $password = $this->passwords[$channelName];
            $room = DOG_Room::getOrCreate($server, $channelName);
            $room->saveVar('room_password', $password);
            unset($this->passwords[$channelName]);
        }
    }
    
}
