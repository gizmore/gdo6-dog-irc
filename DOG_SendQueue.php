<?php
namespace GDO\DogIRC;

use GDO\Dog\DOG_Connector;

final class DOG_SendQueue
{
    private $connector;
    private $throttle;
    private $queue = [];
    
    
    public function __construct(DOG_Connector $connector)
    {
        $this->connector = $connector;
        $this->throttle = $connector->server->getThrottle();
    }
    
    public function sendQueue()
    {
        
    }
    
    
    
}
