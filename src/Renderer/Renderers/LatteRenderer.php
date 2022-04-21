<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


use Latte\Engine;
use Latte\Runtime\FilterInfo;

final class LatteRenderer extends BaseTemplateRenderer
{
	public function isCompatible(string $format): bool
	{
		return $format === 'latte';
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function render(string $template, array $parameters = []): string
	{
		if (isset($parameters['templatePath']) === false) {
			throw new \RuntimeException('Latte Engine require real disk path: Template path does not exist.');
		}
		if (is_string($parameters['templatePath']) === false) {
			throw new \RuntimeException(sprintf(
				'Latte Engine require real disk path: Template path must be a string, but type "%s" given.',
				get_debug_type($parameters['templatePath']),
			));
		}

		return $this->getEngine()->renderToString($parameters['templatePath'], $parameters);
	}


	private function getEngine(): Engine
	{
		$engine = new Engine;
		$translator = $this->translator;
		if ($translator !== null) {
			$engine->addFilter('translate', fn(FilterInfo $fi, ...$args): string => $translator->translate(...$args));
		}

		return $engine;
	}
}
