<?php
namespace GDO\DogIRC\Connector;

use GDO\Dog\DOG_Connector;
use GDO\Core\Application;
use GDO\Core\Logger;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\Dog\DOG_User;
use GDO\Util\Strings;
use GDO\Util\Random;
use GDO\DogIRC\IRCLib;
use GDO\DogIRC\DOG_IRCServerSettings;
use GDO\Dog\Obfuscate;

/**
 * IRC Connector
 * @author gizmore
 * @version 6.10
 * @since 3.00
 */
class IRC extends DOG_Connector
{
	private $timestamp;
	private $socket;
	private $context;
	private $registered = false;
	public $nickname;
	
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
    	if (false === ($this->context = @stream_context_create()))
    	{
    		Logger::logError('IRC Connector cannot create stram context.');
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
    		Logger::logError("Dog_IRC::connect() ERROR: stream_socket_client(): URL={$this->server->getURL()} CONNECT_TIMEOUT={$this->server->getConnectTimeout()}");
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
        fclose($this->socket);
        $this->socket = null;
        $this->connected(false);
        $this->server->disconnect();
    }
    
    public function readMessage()
	{
        if (!$this->socket)
        {
            return false;
        }
		if (feof($this->socket))
		{
			$this->disconnect('I got feof!');
			return false;
		}
		if ($raw = fgets($this->socket, 2047))
		{
		    $raw = trim($raw);
		    if (defined('GWF_CONSOLE_VERBOSE'))
		    {
		        Logger::logCron(sprintf('%s << %s', $this->server->displayName(), $raw));
		    }
    		return $this->parseMessage($raw);
		}
		return false;
	}
	
	private function parseMessage($raw)
	{
	    if (Strings::startsWith($raw, 'ERROR'))
	    {
	        Dog::instance()->event('irc_ERROR', $this->server, Strings::substrFrom($raw, 'ERROR :'));
	        return false;
	    }
	    
	    $by_space = preg_split('/[ ]+/', $raw);
	    
	    $from = $raw[0] === ':' ? ltrim(array_shift($by_space), ':') : '';
	    $from = Strings::substrTo($from, '!', $from);
	    $event = preg_replace('/[^a-z_0-9]/i', '', array_shift($by_space));
	    $args = [];
	    
	    $len = count($by_space);
	    while ($len)
	    {
	        $arg = array_shift($by_space);
	        if (strlen($arg) === 0)
	        {
	            # trailing spaces?
	        }
	        elseif ($arg[0] === ':')
	        {
	            # implode everything after colon
	            $args[] = trim(substr($arg, 1).' '.implode(' ', $by_space));
	            break;
	        }
	        else
	        {
	            # Normal arg
	            $args[] = $arg;
	        }
	        $len--;
	    }
	    
	    if ($from)
	    {
	        $user = DOG_User::getOrCreateUser($this->server, $from);
	        $this->server->addUser($user);
	        array_unshift($args, $user);
	    }
	    
	    array_unshift($args, $this->server);
	    
	    Dog::instance()->event("irc_{$event}", ...$args);
	    
	    if (!$this->registered)
	    {
	        $this->sendAuth();
	        $this->registered = true;
	    }
	    
	    return true;
	}
	
	private function getNickname()
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
	    $nick = $this->getNickname();
	    
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
	    return $this->sendPRIVMSG($room->getName(), $text);
	}
	
	public function sendToUser(DOG_User $user, $text)
	{
	    return $this->sendPRIVMSG($user->getName(), $text);
	}
	
	public function sendNoticeToUser(DOG_User $user, $text)
	{
	    return $this->sendNOTICE($user->getName(), $text);
	}
	
	public function send($text)
	{
	    if ($this->socket)
	    {
	        if (defined('GWF_CONSOLE_VERBOSE'))
	        {
	            Logger::logCron(sprintf('%s >> %s', $this->server->displayName(), $text));
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
	 * Send a message split into multiple.
	 * @param string $message The real message.
	 * @param int $split_len The length for each chunk.
	 */
	public function sendSplitted($message, $split_len=420)
	{
	    $len = strlen($message);
	    
	    if ($len <= $split_len)
	    {
	        return $this->send($message);
	    }
	    
	    if (Strings::startsWith($message, "NOTICE ") || Strings::startsWith($message, "PRIVMSG"))
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
