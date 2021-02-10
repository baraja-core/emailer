<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\HtmlToText\Html2Text;
use Nette\Utils\DateTime;

final class Message extends \Nette\Mail\Message
{
	public const URGENT = 0;

	protected ?\DateTimeImmutable $sendEarliestAt = null;

	private ?string $locale = null;


	public function addTo(string $email, string $name = null): self
	{
		if ($this->getHeader('To') === null) {
			parent::addTo($email, $name);
		} else {
			$this->addCc($email, $name);
		}

		return $this;
	}


	public function setPriority(int $priority): self
	{
		if ($priority < 0) {
			$priority = 0;
		}
		if ($priority > 3) {
			$priority = 3;
		}

		parent::setPriority($priority);

		return $this;
	}


	public function isUrgent(): bool
	{
		return $this->getPriority() === self::URGENT;
	}


	public function getSendEarliestAt(): \DateTimeImmutable
	{
		return $this->sendEarliestAt ?? new \DateTimeImmutable('now');
	}


	/**
	 * @param string|int|\DateTimeInterface $date
	 */
	public function setSendEarliestAt($date): void
	{
		$this->sendEarliestAt = new \DateTimeImmutable(DateTime::from($date)->format('Y-m-d H:i:s.u'));
	}


	public function setLocale(?string $locale): void
	{
		$this->locale = $locale;
	}


	protected function buildText(string $html): string
	{
		return Html2Text::convertHTMLToPlainText($html, $this->locale ?? 'en');
	}
}
