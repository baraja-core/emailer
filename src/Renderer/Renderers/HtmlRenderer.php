<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


final class HtmlRenderer extends BaseTemplateRenderer
{
	public function isCompatible(string $format): bool
	{
		return $format === 'html';
	}


	/**
	 * @param mixed[] $parameters
	 */
	public function render(string $template, array $parameters = []): string
	{
		return $template;
	}
}
