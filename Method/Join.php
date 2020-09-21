<?php
namespace GDO\DogIRC\Method;

use GDO\Dog\DOG_Message;
use GDO\DogIRC\DOG_IRCCommand;
use GDO\DB\GDT_String;
use GDO\Core\GDT_Secret;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\Dog\DOG_User;
use GDO\DogIRC\Connector\IRC;
use GDO\DB\GDT_Checkbox;

/**
 * Make the bot join a channel.
 * @author gizmore
 */
final class Join extends DOG_IRCCommand
{
    public $trigger = 'join_channel';
    
    private $passwords = array();
    
    public function getPermission() { return Dog::HALFOP; }
    
    public function getConfigRoom()
    {
        return array(
            GDT_Checkbox::make('autojoin')->notNull()->initial('1'),
        );
    }
    
    public function gdoParameters()
    {
        return array(
            GDT_String::make('channel')->notNull(),
            GDT_Secret::make('password'),
        );
    }
    
    public function dogExecute(DOG_Message $message, $roomName, $password)
    {
        /** @var \GDO\DogIRC\Connector\IRC $connector **/
        $connector = $message->server->getConnector();
        $command = "JOIN $roomName";
        $command .= $password ? " $password" : '';
        
        if ($room = DOG_Room::getByName($message->server, $roomName))
        {
            $this->setConfigValueRoom($room, 'autojoin', true);
        }
        
        if ($message->server->hasRoom($room))
        {
            return $message->rply('err_dog_already_in_room', [$roomName]);
        }

        $message->rply('msg_join_irc_channel', [$roomName]);
        $this->passwords[$roomName] = $password;
        $connector->send($command);
    }
    
    public function irc_JOIN(DOG_Server $server, DOG_User $user, $roomName)
    {
        if (isset($this->passwords[$roomName]))
        {
            $password = $this->passwords[$roomName];
            $room = DOG_Room::getOrCreate($server, $roomName);
            $room->saveVar('room_password', $password);
            unset($this->passwords[$roomName]);
        }
    }
    
    public function irc_001(DOG_Server $server, DOG_User $user, $text)
    {
        /**
         * @var DOG_Room[] $rooms
         */
        $rooms = DOG_Room::table()->select()->where("room_server={$server->getID()}")->exec()->fetchAllObjects();
        
        /**
         * @var IRC $connector 
         */
        $connector = $server->getConnector();
        
        foreach ($rooms as $room)
        {
            if ($this->getConfigValueRoom($room, 'autojoin'))
            {
                $password = $room->getPassword();
                $command = "JOIN {$room->getName()}";
                $command .= $password ? " {$password}" : '';
                $connector->send($command);
            }
        }
    }
    
}
