<?php

declare(strict_types=1);

namespace Baraja\Emailer;


final class Helper
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . self::class . ' is static and cannot be instantiated.');
	}


	public static function formatDurationFrom(int $fromMicroTime, ?int $nowMicroTime = null): string
	{
		if (($microTime = ($nowMicroTime ?: (int) microtime(true)) - $fromMicroTime) >= 1) {
			return number_format($microTime, 3, '.', ' ') . ' s';
		}

		return number_format($microTime * 1_000, 2, '.', ' ') . ' ms';
	}


	public static function formatMicroTime(int $microTime): string
	{
		return $microTime >= 1
			? number_format($microTime, 3, '.', ' ') . ' s'
			: number_format($microTime * 1_000, 2, '.', ' ') . ' ms';
	}


	public static function userIp(): string
	{
		static $ip = null;
		if ($ip === null) {
			if (isset($_SERVER['REMOTE_ADDR'])) {
				if (\in_array($_SERVER['REMOTE_ADDR'], ['::1', '0.0.0.0', 'localhost'], true)) {
					$ip = '127.0.0.1';
				} elseif (($ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) === false) {
					$ip = '127.0.0.1';
				}
			} else {
				$ip = '127.0.0.1';
			}
		}

		return $ip ?? '127.0.0.1';
	}


	/**
	 * @param mixed[] $left
	 * @param mixed[] $right
	 * @return mixed[]
	 */
	public static function recursiveMerge(array $left, array $right): array
	{
		foreach ($right as $key => $value) {
			if ($value === null || $value === false) {
				if (isset($left[$key]) === false) {
					$left[$key] = $value;
				}
			} else {
				$left[$key] = $value;
			}
		}

		return $left;
	}
}
