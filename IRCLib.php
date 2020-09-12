<?php
namespace GDO\DogIRC;

use GDO\Dog\Dog;

/**
 * IRC utility functions.
 * @author gizmore
 */
final class IRCLib
{
    const CTCP = "\x01";
    const BOLD = "\x02";
    
    public static function mapCharPermission()
    {
        return array(
            '+' => Dog::VOICE,
            '%' => Dog::HALFOP,
            '@' => Dog::OPERATOR,
        );
    }
    
    public static function permissionFromUsername($userName)
    {
        $map = self::mapCharPermission();
        $char = $userName[0];
        if (isset($map[$char]))
        {
            return $map[$char];
        }
    }
    
    public static function trimmedUsername($userName)
    {
        $chars = implode('', array_keys(self::mapCharPermission()));
        return ltrim($userName, $chars);
    }

}
