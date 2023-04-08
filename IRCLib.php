<?php
namespace GDO\DogIRC;

use GDO\Dog\Dog;

/**
 * IRC utility functions.
 *
 * @author gizmore
 */
final class IRCLib
{

	final public const CTCP = "\x01";

	final public const BOLD = "\x02";

	final public const ITALIC = "\x1F";


	public static function bold(string $s): string
	{
		$b = self::BOLD;
		return "{$b}{$s}{$b}";
	}

	public static function italic(string $s): string
	{
		$b = self::ITALIC;
		return "{$b}{$s}{$b}";
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

	public static function mapCharPermission(): array
	{
		return [
			'+' => Dog::VOICE,
			'%' => Dog::HALFOP,
			'&' => Dog::OPERATOR,
			'@' => Dog::OPERATOR,
		];
	}

	public static function trimmedUsername($userName)
	{
		$chars = implode('', array_keys(self::mapCharPermission()));
		return ltrim($userName, $chars);
	}

	/**
	 * Split a message into $len chunks, but only break at spaces.
	 *
	 * @return string[]
	 */
	public static function splitMessage(string $text, int $len = 420): array
	{
		$chunks = [];
		$end1 = $end2 = 0;
		while (true)
		{
			if (mb_strlen($text) <= $len)
			{
				$chunks[] = $text;
				return $chunks;
			}
			$end2 = mb_strpos($text, ' ', $end1 + 1);
			if ($end2 > $len)
			{
				if ($end1 === 0) # NO SPACE!
				{
					$end1 = $len;
				}
				$chunks[] = mb_substr($text, 0, $end1);
				$text = mb_substr($text, $end1);
				$end1 = $end2 = 0;
			}
			$end1 = $end2;
		}
		return $chunks;
	}

}
