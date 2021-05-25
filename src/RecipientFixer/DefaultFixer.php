<?php

declare(strict_types=1);

namespace Baraja\Emailer\RecipientFixer;


final class DefaultFixer implements Fixer
{
	private const DOMAINS = ['seznam.cz', 'gmail.com', 'zoznam.sk', 'centrum.cz', 'atlas.cz', 'atlas.sk'];


	public function fix(string $email): string
	{
		if (preg_match('/^([^@]+)@([^@]+)\.([a-z]{1,6})$/', strtolower(trim($email)), $emailParser)) {
			[, $user, $domainName, $tld] = $emailParser;
			$domain = $domainName . '.' . $tld;
		} else {
			throw new \InvalidArgumentException('Email "' . $email . '" is in invalid format.');
		}
		if (\in_array($domain, self::DOMAINS, true)) {
			return $email;
		}

		return $user . '@' . ($this->getSuggestion($domain, $tld) ?? $domain);
	}


	/**
	 * Looks for a string from possibilities that is most similar to value, but not the same (for 8-bit encoding).
	 */
	private function getSuggestion(string $value, string $tld): ?string
	{
		$best = null;
		$min = (strlen($value) / 4 + 1) * 10 + .1;
		foreach (self::DOMAINS as $item) {
			if ((explode('.', $item)[1] ?? '') !== $tld) {
				continue;
			}
			if ($item !== $value) {
				$len = levenshtein($item, $value, 10, 11, 10);
				if ($len < $min) {
					$min = $len;
					$best = $item;
				}
			}
		}

		return $best;
	}
}
