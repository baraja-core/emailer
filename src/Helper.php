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
		$microTime = ($nowMicroTime ?: (int) microtime(true)) - $fromMicroTime;

		return $microTime >= 1
			? number_format($microTime, 3, '.', ' ') . ' s'
			: number_format($microTime * 1_000, 2, '.', ' ') . ' ms';
	}


	public static function formatMicroTime(int|float $microTime): string
	{
		return $microTime >= 1
			? number_format($microTime, 3, '.', ' ') . ' s'
			: number_format($microTime * 1_000, 2, '.', ' ') . ' ms';
	}


	public static function userIp(): string
	{
		static $ip = null;
		if ($ip === null) {
			if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) { // Cloudflare support
				$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
			} elseif (isset($_SERVER['REMOTE_ADDR']) === true) {
				$ip = $_SERVER['REMOTE_ADDR'];
			} else {
				$ip = '127.0.0.1';
			}
			if (in_array($ip, ['::1', '0.0.0.0', 'localhost'], true)) {
				$ip = '127.0.0.1';
			}
			$filter = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
			if ($filter === false) {
				$ip = '127.0.0.1';
			}
		}

		return $ip;
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
