<?php
declare(strict_types=1);
namespace GDO\DogIRC\Connector;

use GDO\Core\GDT;
use GDO\Core\Logger;
use GDO\Dog\Dog;
use GDO\Dog\DOG_Connector;
use GDO\Dog\DOG_Message;
use GDO\Dog\DOG_Room;
use GDO\Dog\DOG_Server;
use GDO\Dog\DOG_User;
use GDO\Dog\Obfuscate;
use GDO\DogIRC\DOG_IRCServerSettings;
use GDO\DogIRC\IRCLib;
use GDO\User\GDO_User;
use GDO\Util\Random;
use GDO\Util\Strings;

/**
 * IRC Connector
 *
 * @version 7.0.3
 * @since 3.0.0
 * @author gizmore
 */
class IRC extends DOG_Connector
{

	public ?string $nickname = null;

	private $socket;
	private $context;
	private bool $registered = false;

	public function setupServer(DOG_Server $server): void
	{
		$tls = stripos($server->getURL()->raw, 'ircs://') === 0 ? '1' : '0';
		$server->setVar('serv_tls', $tls);
	}

	public function obfuscate(string $string): string
	{
		return Obfuscate::obfuscate($string);
	}

	public function gdtRenderMode(): int
	{
		return GDT::RENDER_IRC;
	}

	public function connect(): bool
	{
		$options = [
			'ssl' => [
				'allow_self_signed' => true,
				'verify_peer' => false,
				'verify_peer_name' => false,
			],
		];

		if (!($this->context = stream_context_create($options)))
		{
			Logger::logError('IRC Connector cannot create stream context.');
			return false;
		}

		$errno = 0;
		$errstr = '';
		if (
			false === ($socket = stream_socket_client(
				$this->server->getConnectURL(),
				$errno,
				$errstr,
				$this->server->getConnectTimeout(),
				STREAM_CLIENT_CONNECT,
				$this->context))
		)
		{
			Logger::logError('IRC Connector cannot create stram context.');
			Logger::logError("Dog_IRC::connect() ERROR: stream_socket_client(): URL={$this->server->getURL()->raw} CONNECT_TIMEOUT={$this->server->getConnectTimeout()}");
			Logger::logError(sprintf('Dog_IRC::connect() $errno=%d; $errstr=%s', $errno, $errstr));
		}

		if ($this->server->getURL()->getScheme() === 'ircs')
		{
			if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
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

		if (!stream_set_blocking($socket, false))
		{
			Logger::logError('Dog_IRC::connect() ERROR: stream_set_blocking(): $blocked=0');
			return false;
		}

		$this->socket = $socket;
		$this->connected(true);
		$this->nickname = null;
		$this->registered = false;
		return true;
	}

	public function disconnect($reason): void
	{
		$this->send("QUIT :{$reason}");
	}

	public function send(string $text): bool
	{
		echo "{$this->server->renderName()} >> {$text}\n";
		if ($this->socket)
		{
			if (!fwrite($this->socket, "$text\r\n"))
			{
				$this->socket = null;
				$this->disconnect('SEND failed');
				return false;
			}
			return true;
		}
		return false;
	}

	public function readMessage(): ?DOG_Message
	{
		if (!$this->socket)
		{
			return null;
		}
		if (feof($this->socket))
		{
// 			$this->disconnect('I got feof!');
			return null;
		}
		if ($raw = fgets($this->socket, 2048))
		{
			$raw = rtrim($raw);
			Logger::logCron(sprintf('%s << %s', $this->server->renderName(), $raw));
			return $this->parseMessage($raw);
		}
		return null;
	}

	/**
	 * Parse and execute the next message.
	 * Optimized for speed.
	 */
	private function parseMessage(string $raw): ?DOG_Message
	{
		if (str_starts_with($raw, 'ERR'))
		{
			Dog::instance()->event('irc_ERROR', $this->server, Strings::substrFrom($raw, 'ERROR :'));
			return null;
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

		return null;
	}

	private function sendAuth(): void
	{
		$nick = $this->getNextNickname();

		if (
			!$this->send(sprintf('USER %s %s %s :%s',
				$nick, 'dog.gizmore.org', $this->server->getDomain(), 'Dawg'))
		)
		{
			return;
		}
		if (!$this->send("NICK {$nick}"))
		{
			return;
		}

		if ($password = $this->server->getPassword())
		{
			if ($nick === $this->server->getUsername())
			{
				$this->sendPRIVMSG('NickServ', 'IDENTIFY ' . $password);
			}
		}
	}

	private function getNextNickname(): string
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

	public function sendPRIVMSG(string $to, string $text): bool
	{
		return $this->sendSplitted("PRIVMSG {$to} :{$text}");
	}

	/**
	 * Makes multi-line work.
	 */
	public function sendSplitted(string $message, int $split_len = 420): bool
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
				$first = false;
			}
		}
		return true;
	}

	/**
	 * Send a message split into multiple.
	 */
	public function sendSplittedB(string $message, int $split_len = 420): bool
	{
		$len = mb_strlen($message);

		if ($len <= $split_len)
		{
			return $this->send($message);
		}

		$prefix = '';
		if (str_starts_with($message, 'NOTICE ') || str_starts_with($message, 'PRIVMSG '))
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

	/**
	 * Generate the next nickname.
	 */
	public function getNickname(): string
	{
		return $this->nickname;
	}

	public function sendToRoom(DOG_Room $room, $text): bool
	{
		parent::sendToRoom($room, $text);
		return $this->sendPRIVMSG($room->getName(), $text);
	}

	public function sendToUser(DOG_User $user, $text): bool
	{
		parent::sendToUser($user, $text);
		return $this->sendPRIVMSG($user->getName(), $text);
	}

	public function sendNoticeToUser(DOG_User $user, $text): bool
	{
		parent::sendNoticeToUser($user, $text);
		return $this->sendNOTICE($user->getName(), $text);
	}

	public function sendNOTICE($to, $text): bool
	{
		return $this->sendSplitted("NOTICE {$to} :{$text}");
	}

	public function getSettings(): DOG_IRCServerSettings
	{
		return DOG_IRCServerSettings::getOrCreate($this->server);
	}

	public function disconnected(): void
	{
		$this->socket = null;
		$this->context = null;
		$this->connected(false);
	}

	public function sendCTCP($to, $text): bool
	{
		$ctcp = IRCLib::CTCP;
		return $this->sendNOTICE($to, "{$ctcp}{$text}{$ctcp}");
	}

	public function sendAction($to, $text): bool
	{
		$ctcp = IRCLib::CTCP;
		return $this->sendPRIVMSG($to, "{$ctcp}ACTION {$text}{$ctcp}");
	}

	public function init(): bool
	{
		return true;
	}


}
