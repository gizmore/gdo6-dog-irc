<?php
namespace GDO\DogIRC;

use GDO\Dog\DogTestCase;
use GDO\Dog\DOG_User;
use GDO\Dog\DOG_Server;
use GDO\Tests\MethodTest;
use GDO\User\GDO_User;
use GDO\User\GDO_UserPermission;
use GDO\Dog\Dog;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Message;

class IRCTestCase extends DogTestCase
{
    public function setUp() : void
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
    
    protected function getServer()
    {
        $server = DOG_Server::getBy('serv_connector', 'IRC');
        return $server ? $server : parent::getServer();
    }
    
    /**
     * @return \GDO\DogIRC\Connector\IRC
     */
    protected function getConnector()
    {
        return $this->getServer()->getConnector();
    }
    
    protected function getDogRoom()
    {
        return DOG_Room::getByName($this->getServer(), '#dog');
    }
    
    public function createUser($username, DOG_Server $server=null)
    {
        $server = $server ? $server : $this->getServer();
        
        $sid = $server->getID();
        $longUsername = "{$username}\{{$sid}\}";
        if (!($user = GDO_User::getBy('user_name', $longUsername)))
        {
            $user = GDO_User::blank([
                'user_name' => $longUsername,
                'user_type' => GDO_User::MEMBER,
            ])->insert();
        }
        
        if (!($doguser = DOG_User::table()->select()->
            where('doguser_server='.$sid)->
            where('doguser_name='.quote($username))->
            first()->exec()->fetchObject()))
        {
            $doguser = DOG_User::blank([
                'doguser_name' => $username,
                'doguser_server' => $sid,
                'doguser_user_id' => $user->getID(),
            ])->insert();
        }
        
        $server->addUser($doguser);
        
        if ($room = $this->getDogRoom())
        {
            $room->addUser($doguser);
        }
        
        return $doguser;
    }
    
    public function ircPrivmsg($text, DOG_Room $room=null, $usleep=500000)
    {
        ob_start();
        ob_implicit_flush(false);
        $server = $this->getServer();
        $message = DOG_Message::make()->
            user($this->doguser)->server($server)->
            room($room)->text($text);
        Dog::instance()->event('dog_message', $message);
        $response = ob_get_contents();
        ob_end_clean();
        ob_implicit_flush(true);
        return $response . "\n". $this->ircResponse($usleep);
    }
    
    public function ircPrivmsgRoom($text, $usleep=500000)
    {
        $room = $this->getDogRoom();
        $text = $room->getTrigger() . $text;
        return $this->ircPrivmsg($text, $room, $usleep);
    }
    
    public function ircResponse($usleep=500000)
    {
        $response = '';
        try
        {
            while (true)
            {
                usleep($usleep);
                ob_implicit_flush(false);
                ob_start();
                Dog::instance()->mainloopStep();
                $r = ob_get_contents();
                $response .= $r;
                ob_end_clean();
                ob_implicit_flush(true);
                if (!$r)
                {
                    break;
                }
            }
        }
        catch (\Throwable $ex)
        {
            ob_implicit_flush(true);
            ob_end_clean();
        }
        finally
        {
           return $response;
        }
    }
    
    protected function userGizmore2()
    {
        return GDO_User::findBy('user_name', 'gizmore{2}');
    }
    
    protected function restoreUserPermissions(GDO_User $user)
    {
        if (count(MethodTest::$USERS))
        {
            $g2 = GDO_User::getByName('gizmore{2}');
            if ($g2)
            {
                if ($user->getID() === $g2->getID())
                {
                    $table = GDO_UserPermission::table();
                    $table->grant($user, 'admin');
                    $table->grant($user, 'staff');
                    $table->grant($user, 'cronjob');
                    $table->grant($user, Dog::VOICE);
                    $table->grant($user, Dog::HALFOP);
                    $table->grant($user, Dog::OPERATOR);
                    $table->grant($user, Dog::OWNER);
                    $user->changedPermissions();
                }
            }
        }
    }
    
}
