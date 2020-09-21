<?php
namespace GDO\DogIRC;

use GDO\Core\GDO_Module;

final class Module_DogIRC extends GDO_Module
{
    public function getDependencies() { return ['Dog']; }
    
    public function onLoadLanguage() { return $this->loadLanguage('lang/irc'); }
    
    public function getClasses()
    {
        return array(
            DOG_IRCServerSettings::class,
        );
    }

}
