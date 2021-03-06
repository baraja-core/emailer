<?php

declare(strict_types=1);

namespace Baraja\Emailer\Email;


use Baraja\DynamicConfiguration\Configuration;
use Baraja\Emailer\Message;

abstract class BaseEmail implements Email
{
	public function __construct(
		protected Configuration $configuration
	) {
	}


	public function getName(): string
	{
		return self::class;
	}


	public function getDescription(): ?string
	{
		return null;
	}


	public function getTemplate(string $locale): ?string
	{
		return null;
	}


	/**
	 * @return string[]|null[]
	 */
	public function getParameters(): array
	{
		return $this->configuration->getMultiple([
			'projectUrl' => 'base-url',
			'projectName' => 'name',
			'projectPhone' => 'phone',
			'projectEmail' => 'admin-email',
			'from',
		], 'project_basic_configuration');
	}


	public function getMessage(): Message
	{
		return new Message;
	}
}
