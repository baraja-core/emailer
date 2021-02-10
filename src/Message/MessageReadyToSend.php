<?php

declare(strict_types=1);

namespace Baraja\Emailer;


final class MessageReadyToSend
{
	private Message $message;

	private Emailer $emailer;


	public function __construct(Message $message, Emailer $emailer)
	{
		$this->message = $message;
		$this->emailer = $emailer;
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
