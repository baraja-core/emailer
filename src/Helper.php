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


	/**
	 * Checks if the value is a valid email address. It does not verify that the domain actually exists,
	 * only the syntax is verified.
	 */
	public static function isEmail(string $value): bool
	{
		$atom = "[-a-z0-9!#$%&'*+/=?^_`{|}~]"; // RFC 5322 unquoted characters in local-part
		$alpha = "a-z\x80-\xFF"; // superset of IDN

		$pattern = sprintf(
			'(^("([ !#-[\\]-~]*|\\\\[ -~])+"|%s+(\\.%s+)*)@([0-9%s]([-0-9%s]{0,61}[0-9%s])?\\.)+[%s]([-0-9%s]{0,17}[%s])?$)Dix',
			$atom,
			$atom,
			$alpha,
			$alpha,
			$alpha,
			$alpha,
			$alpha,
			$alpha,
		);

		return (bool) preg_match($pattern, $value);
	}
}
