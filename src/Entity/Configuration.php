<?php

declare(strict_types=1);

namespace Baraja\Emailer\Entity;


final class Configuration
{
	private string $tempDir;

	private bool $useQueue;

	/** After how many seconds should the process end? */
	private int $queueTimeout = 295;

	/**
	 * Delay between sending individual emails in seconds.
	 * It is also possible to enter "0.5", which means half a second.
	 * Internally, usleep instead of sleep is used.
	 */
	private float $queueEmailDelay = 0.3;

	/** Delay between operations to check if something is in the queue. */
	private int $queueCheckIterationDelay = 2;

	/** @var string[] */
	private array $adminEmails;


	public function __construct(string $tempDir, bool $useQueue, array $adminEmails = [])
	{
		$this->tempDir = $tempDir;
		$this->useQueue = $useQueue;
		$this->adminEmails = $adminEmails;
	}


	public function getTempDir(): string
	{
		return $this->tempDir;
	}


	public function isUseQueue(): bool
	{
		return $this->useQueue;
	}


	public function setUseQueue(bool $useQueue): void
	{
		$this->useQueue = $useQueue;
	}


	/**
	 * @return string[]
	 */
	public function getAdminEmails(): array
	{
		return $this->adminEmails;
	}


	/**
	 * @param string[] $adminEmails
	 */
	public function setAdminEmails(array $adminEmails): void
	{
		$this->adminEmails = $adminEmails;
	}


	public function getQueueTimeout(): int
	{
		return $this->queueTimeout;
	}


	public function setQueueTimeout(int $queueTimeout): void
	{
		$this->queueTimeout = $queueTimeout;
	}


	public function getQueueEmailDelay(): float
	{
		return $this->queueEmailDelay;
	}


	public function setQueueEmailDelay(float $queueEmailDelay): void
	{
		$this->queueEmailDelay = $queueEmailDelay;
	}


	public function getQueueCheckIterationDelay(): int
	{
		return $this->queueCheckIterationDelay;
	}


	public function setQueueCheckIterationDelay(int $queueCheckIterationDelay): void
	{
		$this->queueCheckIterationDelay = $queueCheckIterationDelay;
	}


	public function getMaxAllowedAttempts(): int
	{
		return 5;
	}
}
