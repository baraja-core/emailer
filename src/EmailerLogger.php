<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Baraja\Emailer\Entity\Email;
use Baraja\Emailer\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;

final class EmailerLogger
{
	private EntityManagerInterface $entityManager;


	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @param string $level should be Log::WARNING, ERROR or INFO.
	 */
	public function log(string $level, string $message, ?Email $email = null): void
	{
		$this->entityManager->persist($log = new Log($level, $message, $email));
		$this->entityManager->flush();
	}
}
