<?php

declare(strict_types=1);

namespace Baraja\Emailer\Email;


use Baraja\Emailer\Message;

interface Email
{
	public function getName(): string;

	public function getDescription(): ?string;

	public function getTemplate(string $locale): ?string;

	/**
	 * @return mixed[]
	 */
	public function getParameters(): array;

	public function getMessage(): Message;
}
