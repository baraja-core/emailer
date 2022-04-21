<?php

declare(strict_types=1);

namespace Baraja\Emailer\Entity;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'core__emailer_log')]
class Log
{
	public const
		LevelWarning = 'WARNING',
		LevelError = 'ERROR',
		LevelInfo = 'INFO';

	public const LevelToInt = [
		self::LevelInfo => 1,
		self::LevelWarning => 2,
		self::LevelError => 5,
	];

	public const IntToLevel = [
		1 => self::LevelInfo,
		2 => self::LevelWarning,
		5 => self::LevelError,
	];

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

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
		if (\in_array($level, [self::LevelWarning, self::LevelError, self::LevelInfo], true) === false) {
			trigger_error(__METHOD__ . ': Level "' . $level . '" is not supported.');
			$level = self::LevelError;
		}

		$this->level = self::LevelToInt[$level];
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
		return self::IntToLevel[$this->level];
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
