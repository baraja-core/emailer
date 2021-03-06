<?php

declare(strict_types=1);

namespace Baraja\Emailer;


final class MessageReadyToSend
{
	public function __construct(
		private Message $message,
		private Emailer $emailer
	) {
	}


	public function send(): void
	{
		$this->emailer->send($this->message);
	}


	public function getMessage(): Message
	{
		return $this->message;
	}
}
