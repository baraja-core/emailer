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
	 * @param mixed[] $parameters
	 */
	public function render(string $template, array $parameters = []): string
	{
		if (isset($parameters['templatePath']) === false) {
			throw new \RuntimeException('Latte Engine require real disk path: Template path does not exist.');
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
