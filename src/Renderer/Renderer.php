<?php

declare(strict_types=1);

namespace Baraja\Emailer\Renderer;


interface Renderer
{
	/**
	 * @param array<string, mixed> $parameters
	 */
	public function render(string $template, array $parameters = []): string;

	/**
	 * Can I use this renderer for given format?
	 * For example can I use MjmlRenderer for Latte template?
	 */
	public function isCompatible(string $format): bool;
}
