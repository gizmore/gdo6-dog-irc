<?php
declare(strict_types=1);
namespace GDO\DogIRC;

use GDO\Core\Website;
use GDO\Dog\Dog;

/**
 * IRC utility functions.
 *
 * @author gizmore
 * @version 7.0.3
 */
final class IRCLib
{
	final public const MAX_MSG_LEN = 420;

	final public const CTCP = "\x01";

	final public const BOLD = "\x02";

	final public const ITALIC = "\x1F";

	final public const WHITE = '00';

	final public const BLACK = '01';

	final public const BLUE = '02';

	final public const GREEN = '03';

	final public const RED = '04';

	final public const BROWN = '05';

	final public const MAGENTA = '06';

	final public const ORANGE = '07';


	public static function bold(string $s): string
	{
		$b = self::BOLD;
		return "{$b}{$s}{$b}";
	}

	public static function italic(string $s): string
	{
		$i = self::ITALIC;
		return "{$i}{$s}{$i}";
	}

	/**
	 * @TODO: Turn a string into an IRC colored string.
	 */
	public static function colored(string $s, string $ircColor): string
	{
		Website::errorRaw('IRCLIB', 'IRCLIB Color not working yet. Color: ' . $ircColor);
		return $s;
	}


	public static function permissionFromUsername(string $userName): string
	{
		$map = self::mapCharPermission();
		$char = $userName[0];
		return $map[$char]?: '';
	}

	public static function mapCharPermission(): array
	{
		return [
			'+' => Dog::VOICE,
			'%' => Dog::HALFOP,
			'@' => Dog::OPERATOR,
			'&' => Dog::OPERATOR,
			'!' => Dog::OPERATOR,
		];
	}

	public static function trimmedUsername(string $userName): string
	{
		$chars = implode('', array_keys(self::mapCharPermission()));
		return ltrim($userName, $chars);
	}

	/**
	 * Split a message into $len chunks, but only break at spaces.
	 *
	 * @return string[]
	 */
	public static function splitMessage(string $text, int $len = self::MAX_MSG_LEN): array
	{
		$end1 = 0;
		$chunks = [];
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
				$end2 = 0;
			}
			$end1 = $end2;
		}
	}

}
