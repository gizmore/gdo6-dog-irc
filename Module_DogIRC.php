<?php
declare(strict_types=1);
namespace GDO\DogIRC;

use GDO\Core\GDO_Module;

/**
 * IRC Connector and basics for the Dog GDOv7 Chatbot.
 * @author gizmore
 * @version 7.0.3
 * @since 3.2.0
 */
final class Module_DogIRC extends GDO_Module
{

	public int $priority = 45;

	public function getDependencies(): array { return ['DogAuth']; }

	public function onLoadLanguage(): void { $this->loadLanguage('lang/irc'); }

	public function getClasses(): array
	{
		return [
			DOG_IRCServerSettings::class,
		];
	}

}
