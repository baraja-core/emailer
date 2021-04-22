<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;

final class EmailerLogger
{
	public function __construct(
		private EntityManagerInterface $entityManager
	) {
	}


	/**
	 * @param string $level should be Log::WARNING, ERROR or INFO.
	 */
	public function log(string $level, string $message, ?Email $email = null): void
	{
		$this->entityManager->persist(new Log($level, $message, $email));
		$this->entityManager->flush();
	}
}
