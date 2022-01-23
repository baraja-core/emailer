<?php

declare(strict_types=1);

namespace Baraja\Emailer\Entity;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'core__emailer_log')]
class Log
{
	public const
		LEVEL_WARNING = 'WARNING',
		LEVEL_ERROR = 'ERROR',
		LEVEL_INFO = 'INFO';

	public const LEVEL_TO_INT = [
		self::LEVEL_INFO => 1,
		self::LEVEL_WARNING => 2,
		self::LEVEL_ERROR => 5,
	];

	public const INT_TO_LEVEL = [
		1 => self::LEVEL_INFO,
		2 => self::LEVEL_WARNING,
		5 => self::LEVEL_ERROR,
	];

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	private int $id;

	#[ORM\Column(type: 'smallint')]
	private int $level;

	#[ORM\Column(type: 'text')]
	private string $message;

	#[ORM\ManyToOne(targetEntity: Email::class)]
	private ?Email $email;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private \DateTimeInterface $insertedDate;


	public function __construct(string $level, string $message, ?Email $email = null)
	{
		$level = strtoupper($level);
		if (\in_array($level, [self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_INFO], true) === false) {
			trigger_error(__METHOD__ . ': Level "' . $level . '" is not supported.');
			$level = self::LEVEL_ERROR;
		}

		$this->level = self::LEVEL_TO_INT[$level];
		$this->message = $message;
		$this->email = $email;
		$this->insertedDate = new \DateTimeImmutable('now');
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getLevelNumber(): int
	{
		return $this->level;
	}


	/** Formatted level name. */
	public function getLevel(): string
	{
		return self::INT_TO_LEVEL[$this->level];
	}


	public function getMessage(): string
	{
		return $this->message;
	}


	public function getEmail(): ?Email
	{
		return $this->email;
	}


	public function getInsertedDate(): ?\DateTimeInterface
	{
		return $this->insertedDate;
	}


	/** @internal */
	public function setInsertedDate(?\DateTimeInterface $insertedDate): void
	{
		$this->insertedDate = $insertedDate ?? new \DateTimeImmutable('now');
	}
}
