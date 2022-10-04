<?php
namespace GDO\DogIRC\Connector;

use GDO\Date\Time;
use GDO\Dog\DOG_Connector;
use GDO\Core\Application;
use GDO\Core\Logger;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\Dog\DOG_User;
use GDO\User\GDO_User;
use GDO\Util\Strings;
use GDO\Util\Random;
use GDO\DogIRC\IRCLib;
use GDO\DogIRC\DOG_IRCServerSettings;
use GDO\Dog\Obfuscate;
use GDO\Language\Trans;

/**
 * IRC Connector
 * @author gizmore
 * @version 6.10
 * @since 3.00
 */
class IRC extends DOG_Connector
{
	public $nickname;

	private $timestamp;
	private $socket;
	private $context;
	private $registered = false;
	
	public function setupServer(DOG_Server $server)
	{
	    $tls = strpos($server->getURL()->raw, 'ircs://') === 0 ? '1' : '0';
	    $server->setVar('serv_tls', $tls);
	}
	
	public function obfuscate($string)
	{
	    return Obfuscate::obfuscate($string);
	}
	
	public function getSettings()
	{
	    return DOG_IRCServerSettings::getOrCreate($this->server);
	}
	
    public function connect()
    {
        $options = [
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
        
    	if (false === ($this->context = stream_context_create($options)))
    	{
    		Logger::logError('IRC Connector cannot create stream context.');
    		return false;
    	}
    	
    	$errno = 0; $errstr = '';
    	if (false === ($socket = @stream_socket_client(
    		$this->server->getConnectURL(),
    		$errno,
    		$errstr,
    		$this->server->getConnectTimeout(),
    		STREAM_CLIENT_CONNECT,
    		$this->context)))
    	{
    		Logger::logError('IRC Connector cannot create stram context.');
    		Logger::logError("Dog_IRC::connect() ERROR: stream_socket_client(): URL={$this->server->getURL()->raw} CONNECT_TIMEOUT={$this->server->getConnectTimeout()}");
    		Logger::logError(sprintf('Dog_IRC::connect() $errno=%d; $errstr=%s', $errno, $errstr));
    	}
    	
    	if ($this->server->getURL()->getScheme() === 'ircs')
    	{
    		if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
    		{
    			Logger::logError('Dog_IRC::connect() ERROR: stream_socket_enable_crypto(true, STREAM_CRYPTO_METHOD_TLS_CLIENT)');
    			return false;
    		}
    	}
    	
//     	if (!@stream_set_timeout($socket, $this->server->getConnectTimeout()))
//     	{
//     	    Logger::logError('Dog_IRC::connect() ERROR: stream_set_timeout()');
//     	    return false;
//     	}
    	
    	if (!@stream_set_blocking($socket, 0))
    	{
    		Logger::logError('Dog_IRC::connect() ERROR: stream_set_blocking(): $blocked=0');
    		return false;
    	}
    	
    	$this->timestamp = Application::$TIME;
    	$this->socket = $socket;
    	$this->connected(true);
    	$this->nickname = null;
    	$this->registered = false;
    	return true;
    }
    
    public function disconnect($reason)
    {
        $this->send("QUIT :{$reason}");
    }
    
    public function disconnected()
    {
        $this->socket = null;
        $this->context = null;
        $this->connected(false);
    }
    
    public function readMessage()
	{
        if (!$this->socket)
        {
            return false;
        }
		if (feof($this->socket))
		{
// 			$this->disconnect('I got feof!');
			return false;
		}
		if ($raw = fgets($this->socket, 2047))
		{
		    $raw = rtrim($raw);
		    if (defined('GDO_CONSOLE_VERBOSE'))
		    {
		        Logger::logCron(sprintf('%s << %s', $this->server->renderName(), $raw));
// 		        ob_flush();
		    }
    		return $this->parseMessage($raw);
		}
		return false;
	}
	
	/**
	 * Parse and execute the next message.
	 * Optimized for speed.
	 * @author gizmore
	 * @version 6.10
	 * @since 6.10
	 * @param string $raw
	 * @return boolean
	 */
	private function parseMessage($raw)
	{
	    if (strpos($raw, 'ERR') === 0)
	    {
	        Dog::instance()->event('irc_ERROR', $this->server, Strings::substrFrom($raw, 'ERROR :'));
	        return false;
	    }
	    
	    $from = '';
	    if ($raw[0] === ':')
	    {
	        $raw = substr($raw, 1);
	        $i = strpos($raw, ' ');
	        $from = substr($raw, 0, $i);
	        $raw = substr($raw, $i + 1);
	        if (false !== ($i = strpos($from, '!')))
	        {
	            $from = substr($from, 0, $i);
	        }
	    }

	    $i = strpos($raw, ' ');
	    $event = substr($raw, 0, $i);
	    $raw = substr($raw, $i + 1);
	    
	    $args = [$this->server];
	    
	    if ($from)
	    {
	        $args[] = $user = DOG_User::getOrCreateUser($this->server, $from);
	        $gdoUser = $user->getGDOUser();
	        GDO_User::setCurrent($gdoUser);
	        Trans::setISO($gdoUser->getLangISO());
	        Time::setTimezone($gdoUser->getTimezone());
	        $this->server->addUser($user);
	    }
	    
	    while ($raw)
	    {
	        if ($raw[0] === ':')
	        {
	            $args[] = substr($raw, 1);
	            break;
	        }
	        
	        elseif (false === ($i = strpos($raw, ' ')))
	        {
	            $args[] = $raw;
	            break;
	        }

	        $args[] = substr($raw, 0, $i);
    	    $raw = substr($raw, $i + 1);
	    }

	    Dog::instance()->event("irc_{$event}", ...$args);
	    
	    if (!$this->registered)
	    {
	        $this->sendAuth();
	        $this->registered = true;
	    }
	    
	    return true;
	}
	
	/**
	 * Generate the next nickname.
	 * @return string
	 */
	public function getNickname()
	{
	    return $this->nickname;
	}
	
	private function getNextNickname()
	{
	    if ($this->nickname === null)
	    {
	        $this->nickname = $this->server->getUsername();
	    }
	    else
	    {
	        $this->nickname = $this->server->getUsername() . Random::randomKey(4, Random::NUMERIC);
	    }
	    
	    return $this->nickname;
	}
	
	private function sendAuth()
	{
	    $nick = $this->getNextNickname();
	    
	    if (!$this->send(sprintf('USER %s %s %s :%s', 
	        $nick, 'dog.gizmore.org', $this->server->getDomain(), 'Dawg')))
	    {
	        return false;
	    }
	    if (!$this->send("NICK {$nick}"))
	    {
	        return false;
	    }
	    
	    if ($password = $this->server->getPassword())
	    {
	        if ($nick === $this->server->getUsername())
	        {
    	        if (!$this->sendPRIVMSG('NickServ', 'IDENTIFY '.$password))
    	        {
    	            return false;
    	        }
	        }
	    }
	    return true;
	}
	
	public function sendCTCP($to, $text)
	{
	    $ctcp = IRCLib::CTCP;
	    return $this->sendNOTICE($to, "{$ctcp}{$text}{$ctcp}");
	}
	
	public function sendAction($to, $text)
	{
	    $ctcp = IRCLib::CTCP;
	    return $this->sendPRIVMSG($to, "{$ctcp}ACTION {$text}{$ctcp}");
	}
	
	public function sendNOTICE($to, $text)
	{
	    return $this->sendSplitted("NOTICE {$to} :{$text}");
	}
	
	public function sendPRIVMSG($to, $text)
	{
		return $this->sendSplitted("PRIVMSG {$to} :{$text}");
	}
	
	public function sendToRoom(DOG_Room $room, $text)
	{
	    parent::sendToRoom($room, $text);
	    return $this->sendPRIVMSG($room->getName(), $text);
	}
	
	public function sendToUser(DOG_User $user, $text)
	{
	    parent::sendToUser($user, $text);
	    return $this->sendPRIVMSG($user->getName(), $text);
	}
	
	public function sendNoticeToUser(DOG_User $user, $text)
	{
	    parent::sendNoticeToUser($user, $text);
	    return $this->sendNOTICE($user->getName(), $text);
	}
	
	public function send($text)
	{
	    if ($this->socket)
	    {
	        if (defined('GDO_CONSOLE_VERBOSE'))
	        {
	            Logger::logCron(sprintf('%s >> %s', $this->server->renderName(), $text));
// 	            ob_flush();
	        }
    	    if (!fwrite($this->socket, "$text\r\n"))
    	    {
    	        $this->socket = null;
    	        $this->disconnect("SEND failed");
    	        return false;
    	    }
    	    return true;
	    }
	    return false;
	}
	
	/**
	 * Makes multi-line work.
	 * @param string $message
	 * @param number $split_len
	 */
	public function sendSplitted($message, $split_len=420)
	{
	    $prefix = Strings::substrTo($message, ':') . ':';
	    $messages = explode("\n", $message);
	    $first = true;
	    foreach ($messages as $message)
	    {
	        if ($message = trim($message))
	        {
    	        if (!$first)
    	        {
    	            $message = $prefix . $message;
    	        }
    	        $this->sendSplittedB($message);
	        }
	        $first = false;
	    }
	}
	
	/**
	 * Send a message split into multiple.
	 * @param string $message The real message.
	 * @param int $split_len The length for each chunk.
	 */
	public function sendSplittedB($message, $split_len=420)
	{
	    $len = strlen($message);
	    
	    if ($len <= $split_len)
	    {
	        return $this->send($message);
	    }
	    
	    if (str_starts_with($message, "NOTICE ") || str_starts_with($message, "PRIVMSG"))
	    {
	        $prefix = Strings::substrTo($message, ':') . ':';
	        $message = Strings::substrFrom($message, ':');
	    }
	    
	    foreach (IRCLib::splitMessage($message, $split_len) as $chunk)
	    {
	        if (!$this->send($prefix . $chunk))
	        {
	            return false;
	        }
	    }

	    return true;
	}
	
}
