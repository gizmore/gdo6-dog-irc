<?php
namespace GDO\DogIRC\Connector;

use GDO\Dog\DOG_Connector;
use GDO\Core\Logger;
use GDO\Dog\DOG_Server;
use GDO\Dog\Dog;
use GDO\Dog\DOG_User;
use GDO\Util\Strings;
use GDO\Util\Random;

class IRC extends DOG_Connector
{
	private $timestamp;
	private $socket;
	private $context;
	private $registered = false;
	private $nickname;
	
	public function setupServer(DOG_Server $server)
	{
	    $tls = strpos($server->getURL()->raw, 'ircs://') === 0 ? '1' : '0';
	    $server->setVar('serv_tls', $tls);
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
    	
    	if ($this->server->isTLS())
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
    	
    	$this->timestamp = time();
    	$this->socket = $socket;
    	$this->connected(true);
    	$this->nickname = null;
    	return true;
    }
    
    public function disconnect($reason)
    {
        $this->send("QUIT :{$reason}");
        fclose($this->socket);
        $this->socket = null;
        $this->connected(false);
        $this->registered = false;
    }
    
    public function readMessage()
	{
		if (feof($this->socket))
		{
			$this->disconnect('I got feof!');
			return false;
		}
		if ($raw = fgets($this->socket, 2047))
		{
		    $raw = trim($raw);
    		Logger::logCron(sprintf('%s << %s', $this->server->displayName(), $raw));
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
    	        if (!$this->sendTo('NickServ', 'IDENTIFY '.$password))
    	        {
    	            return false;
    	        }
	        }
	    }
	    return true;
	}
	
	public function sendTo($to, $text)
	{
		return $this->send("PRIVMSG {$to} :{$text}");
	}
	
	public function send($text)
	{
	    Logger::logCron(sprintf('%s >> %s', $this->server->displayName(), $text));
	    if (!fwrite($this->socket, "$text\r\n"))
	    {
	        $this->disconnect("SEND failed");
	        return false;
	    }
	    return true;
	}
	
}
