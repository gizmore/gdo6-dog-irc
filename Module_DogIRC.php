<?php
namespace GDO\DogIRC;

use GDO\Core\GDO_Module;

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
