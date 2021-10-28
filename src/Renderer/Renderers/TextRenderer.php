<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


final class TextRenderer extends BaseTemplateRenderer
{
	public function isCompatible(string $format): bool
	{
		return $format === 'txt';
	}


	/**
	 * @param array<string, mixed> $parameters
	 */
	public function render(string $template, array $parameters = []): string
	{
		return htmlspecialchars($template, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
}
