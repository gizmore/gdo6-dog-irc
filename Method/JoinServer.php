<?php
declare(strict_types=1);
namespace GDO\DogIRC\Method;

use GDO\Core\GDT_Secret;
use GDO\Dog\Dog;
use GDO\Dog\DOG_Message;
use GDO\Dog\DOG_Server;
use GDO\Dog\DOG_User;
use GDO\DogAuth\Method\Super;
use GDO\DogIRC\DOG_IRCCommand;
use GDO\Net\GDT_Url;
use GDO\Net\URL;
use GDO\User\GDT_Username;
use GDO\Util\Random;

/**
 * Join a new IRC network.
 *
 * @version 7.0.3
 * @since 6.10
 * @author gizmore
 */
final class JoinServer extends DOG_IRCCommand
{

	public int $priority = 10;

	public function getCLITrigger(): string
	{
		return 'irc.connect';
	}

	public function getPermission(): ?string { return Dog::OPERATOR; }

	protected function getConnectors(): array { return ['IRC', 'Bash']; }

	public function gdoParameters(): array
	{
		return [
			GDT_Url::make('url')->schemes('irc', 'ircs')->allowInternal()->allowExternal(true, false)->notNull(),
			GDT_Username::make('nickname')->initial($this->getDefaultNickname()),
			GDT_Secret::make('password'),
		];
	}

	public function dogExecute(DOG_Message $message, URL $url, string $nickname = null, string $password = null): bool
	{
		$server = DOG_Server::blank([
			'serv_url' => $url->raw,
			'serv_connector' => 'IRC',
			'serv_username' => $nickname,
			'serv_password' => $password,
			'serv_connect_timeout' => '5',
		]);

		$server->connectionAttemptMax = 3;

		$server->tempSet('irc_join_network', $message->user);

		Dog::instance()->servers[] = $server;

		return $message->rply('msg_dog_joining_irc_network', [$url->getHost()]);
	}

	##############
	### Events ###
	##############
	public function dog_server_failed(DOG_Server $server): void
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

	public function dog_server_connected(DOG_Server $server): void
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

	public function dog_server_authenticated(DOG_Server $server): void
	{
		/**
		 * @var DOG_User $user
		 */
		if ($user = $server->tempGet('irc_join_network'))
		{
			$server->tempUnset('irc_join_network');
			$server->connectionAttemptMax = 50;

			$pw = Random::randomKey(8);
			Super::make()->setConfigValueServer($server, 'super_password', $pw);

			$user->send(t('msg_dog_irc_server_fresh', [$server->renderName(), $server->getConnector()->nickname, $pw]));
		}
	}

}
