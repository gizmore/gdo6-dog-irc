<?php
namespace GDO\DogIRC\Method;

use GDO\DogIRC\DOG_IRCCommand;
use GDO\Dog\DOG_Message;
use GDO\Net\GDT_Url;
use GDO\User\GDT_Username;
use GDO\Core\GDT_Secret;
use GDO\Net\URL;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\DogAuth\Method\Super;
use GDO\Util\Random;
use GDO\Dog\DOG_User;

/**
 * Join a new IRC network.
 * @author gizmore
 * @version 6.10
 * @since 6.10
 */
final class JoinServer extends DOG_IRCCommand
{
    public $priority = 10;
    public $trigger = 'join_network';
    
    public function getPermission() : ?string { return Dog::OPERATOR; }

    public function getConnectors() { return ['IRC', 'Bash']; }
    
    public function gdoParameters() : array
    {
        return array(
            GDT_Url::make('url')->schemes('irc', 'ircs')->allowInternal()->notNull(),
            GDT_Username::make('nickname')->initial($this->getDefaultNickname()),
            GDT_Secret::make('password'),
        );
    }
    
    public function dogExecute(DOG_Message $message, URL $url, $nickname, $password)
    {
        $server = DOG_Server::blank(array(
            'serv_url' => $url->raw,
            'serv_connector' => 'IRC',
            'serv_username' => $nickname,
            'serv_password' => $password,
            'serv_connect_timeout' => '5',
        ));
        
        $server->connectionAttemptMax = 3;
        
        $server->tempSet('irc_join_network', $message->user);
        
        Dog::instance()->servers[] = $server;
        
        return $message->rply('msg_dog_joining_irc_network', [$url->getHost()]);
    }

    ##############
    ### Events ###
    ##############
    public function dog_server_failed(DOG_Server $server)
    {
        /**
         * @var DOG_User $user
         */
        if ($user = $server->tempGet('irc_join_network'))
        {
            $server->tempUnset('irc_join_network');
            Dog::instance()->removeServer($server);
            $server->delete();
            $user->send(t('err_dog_join_network_failed'));
        }
    }
    
    public function dog_server_connected(DOG_Server $server)
    {
        /**
         * @var DOG_User $user
         */
        if ($user = $server->tempGet('irc_join_network'))
        {
            $server->insert();
            $user->send(t('msg_dog_irc_server_connected', [$server->renderName()]));
        }
    }
    
    public function dog_server_authenticated(DOG_Server $server)
    {
        /**
         * @var DOG_User $user 
         */
        if ($user = $server->tempGet('irc_join_network'))
        {
            $server->tempUnset('irc_join_network');
            $server->connectionAttemptMax = 50;
            
            $pw = Random::randomKey(8, Random::ALPHANUMUPLOW);
            Super::byTrigger('super')->setConfigValueServer($server, 'super_password', $pw);
            
            $user->send(t('msg_dog_irc_server_fresh', [$server->renderName(), $server->getConnector()->nickname, $pw]));
        }
    }
}
