<?php
namespace GDO\DogIRC;

use GDO\Dog\DOG_Command;

class DOG_IRCCommand extends DOG_Command
{
    public function getConnectors() { return ['IRC']; }
    
    public function getGroup() { return 'IRC'; }
}
