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
    
    /**
     * Split a message into $len chunks, but only break at spaces.
     * @param string $text
     * @param number $len
     * @return string[]
     */
    public static function splitMessage($text, $len=420)
    {
        $chunks = [];
        $end1 = $end2 = 0;
        while ($text)
        {
            if (strlen($text) <= $len)
            {
                $chunks[] = $text;
                return $chunks;
            }
            $end2 = strpos($text, " ", $end1+1);
            if ($end2 > $len)
            {
                if ($end1 === 0) # NO SPACE!
                {
                    $end1 = $len;
                }
                $chunks[] = substr($text, 0, $end1);
                $text = substr($text, $end1);
                $end1 = $end2 = 0;
            }
            $end1 = $end2;
        }
    }

}
