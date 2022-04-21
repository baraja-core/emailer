<?php

declare(strict_types=1);

namespace Baraja\Emailer;


final class TemplateValidator
{
	private const Patterns = [
		'latte' => '~{\$([a-zA-Z0-9_]+)~',
		'twig' => '~{{\s*([a-zA-Z0-9_]+)~',
		'txt' => '~{{\s*([a-zA-Z0-9_]+)~',
	];


	/**
	 * @param array<int, string> $params
	 */
	public function isAllParametersIncluded(string $content, string $format, array $params): bool
	{
		$hydratedParameters = $this->hydrateParameters($content, $format);
		foreach ($params as $param) {
			if (in_array($param, $hydratedParameters, true) === false) {
				return false;
			}
		}

		return true;
	}


	/**
	 * @return array<int, string>
	 */
	public function hydrateParameters(string $content, string $format): array
	{
		if (
			isset(self::Patterns[$format])
			&& preg_match_all(self::Patterns[$format], $content, $parser) > 0
		) {
			return $parser[1] ?? [];
		}

		return [];
	}
}
