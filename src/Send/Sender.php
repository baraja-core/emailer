<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Nette\Mail\Mailer;
use Nette\Mail\Message as NetteMessage;
use Nette\Mail\SendmailMailer;
use Nette\Mail\SmtpMailer;

final class Sender implements Mailer
{
	/** @var mixed[] */
	private array $config;

	private ?Mailer $mailer = null;


	/**
	 * @param mixed[] $config
	 */
	public function __construct(array $config)
	{
		$this->config = array_merge([
			'smtp' => false,
			'host' => null,
			'port' => null,
			'username' => null,
			'password' => null,
			'secure' => null,
			'timeout' => null,
			'context' => null,
			'clientHost' => null,
			'persistent' => false,
		], $config);
	}


	public function send(NetteMessage $mail): void
	{
		$this->createInstance()->send($mail);
	}


	private function createInstance(): Mailer
	{
		if ($this->mailer === null) {
			$this->mailer = ($this->config['smtp'] ?? false) === true
				? new SmtpMailer($this->config)
				: new SendmailMailer;
		}

		return $this->mailer;
	}
}
