<?php

declare(strict_types=1);

namespace Baraja\Emailer\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\DoctrineMailMessage\DoctrineMessage;
use Baraja\Emailer\Helper;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="core__emailer_email",
 *    indexes={
 *       @Index(name="core__emailer_email_status", columns={"status"})
 *    }
 * )
 */
class Email
{
	use IdentifierUnsigned;

	public const
		STATUS_IN_QUEUE = 'in-queue',
		STATUS_NOT_READY_TO_QUEUE = 'not-ready-to-queue',
		STATUS_WAITING_FOR_NEXT_ATTEMPT = 'waiting-for-next-attempt',
		STATUS_SENT = 'sent',
		STATUS_PREPARING_ERROR = 'preparing-error',
		STATUS_SENDING_ERROR = 'sending-error';

	/** @ORM\OneToOne(targetEntity="\Baraja\DoctrineMailMessage\DoctrineMessage") */
	private DoctrineMessage $message;

	/** @ORM\Column(type="string", length=32) */
	private string $status = self::STATUS_IN_QUEUE;

	/**
	 * If value will be bigger than limit status is changed to 'sending-error'.
	 *
	 * @ORM\Column(type="smallint")
	 */
	private int $failedAttemptsCount = 0;

	/**
	 * How many seconds (to the nearest ms) it took to send the email
	 * (How long did it take to call $mailer->send($mail), so connected to SMTP, etc.).
	 * Used to quickly detect problems with the mail server.
	 *
	 * @ORM\Column(type="decimal", nullable=true)
	 */
	private float|string |null $sendingDuration;

	/**
	 * How many seconds (to the nearest ms) did it take to prepare / generate the e-mail.
	 * It is used for quick detection of problematic situations,
	 * when generating some e-mails can brutally slow down the whole queue.
	 *
	 * @ORM\Column(type="decimal", nullable=true)
	 */
	private float|string |null $preparingDuration;

	/** @ORM\Column(type="string") */
	private string $ip;

	/**
	 * @var string[]|null
	 * @ORM\Column(type="json", nullable=true)
	 */
	private ?array $note = null;

	/** @ORM\Column(type="string", length=2, nullable=true) */
	private ?string $locale = null;

	/**
	 * Date when the message can be sent first (NULL = send as soon as possible).
	 *
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private ?\DateTimeInterface $sendEarliestAt = null;

	/**
	 * Date and time when the next attempt to send the e-mail may occur first (in case of repeated attempts).
	 *
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private ?\DateTimeInterface $sendEarliestNextAttemptAt = null;

	/** @ORM\Column(type="datetime") */
	private \DateTimeInterface $datetimeInserted;

	/**
	 * Date the message was actually sent (NULL = message was not sent).
	 *
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private ?\DateTimeInterface $datetimeSent = null;


	public function __construct(DoctrineMessage $message)
	{
		$this->message = $message;
		$this->ip = Helper::userIp();
		$this->datetimeInserted = new \DateTimeImmutable('now');
	}


	/**
	 * @return string[]
	 */
	public static function getStatuses(): array
	{
		return [
			self::STATUS_IN_QUEUE,
			self::STATUS_NOT_READY_TO_QUEUE,
			self::STATUS_PREPARING_ERROR,
			self::STATUS_SENDING_ERROR,
			self::STATUS_SENT,
			self::STATUS_WAITING_FOR_NEXT_ATTEMPT,
		];
	}


	public function getMessage(): DoctrineMessage
	{
		return $this->message;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function setStatus(string $status): void
	{
		$this->status = \in_array($status, self::getStatuses(), true)
			? $status
			: self::STATUS_PREPARING_ERROR;
	}


	public function getFailedAttemptsCount(): int
	{
		return $this->failedAttemptsCount;
	}


	public function incrementFailedAttemptsCount(int $count = 1): void
	{
		$this->failedAttemptsCount += $count;
	}


	public function getSendingDuration(): ?float
	{
		return $this->sendingDuration === null
			? null
			: (float) $this->sendingDuration;
	}


	public function setSendingDuration(float $sendingDuration): void
	{
		$this->sendingDuration = $sendingDuration;
	}


	public function getPreparingDuration(): ?float
	{
		return $this->preparingDuration === null
			? null
			: (float) $this->preparingDuration;
	}


	public function setPreparingDuration(float $preparingDuration): void
	{
		$this->preparingDuration = $preparingDuration;
	}


	public function getIp(): string
	{
		return $this->ip;
	}


	public function getNote(): ?string
	{
		return $this->note === null
			? null
			: implode("\n", $this->note);
	}


	public function addNote(string $note): void
	{
		if ($this->note === null) {
			$this->note = [];
		}

		$this->note[] = $note;
	}


	public function getLocale(): ?string
	{
		return $this->locale;
	}


	public function setLocale(?string $locale): void
	{
		$this->locale = strtolower(trim($locale ?? '')) ?: null;
	}


	public function getSendEarliestAt(): ?\DateTimeInterface
	{
		return $this->sendEarliestAt;
	}


	public function setSendEarliestAt(?\DateTimeInterface $sendEarliestAt): void
	{
		$this->sendEarliestAt = $sendEarliestAt;
	}


	public function getSendEarliestNextAttemptAt(): ?\DateTimeInterface
	{
		return $this->sendEarliestNextAttemptAt;
	}


	public function setSendEarliestNextAttemptAt(?\DateTimeInterface $sendEarliestNextAttemptAt): void
	{
		$this->sendEarliestNextAttemptAt = $sendEarliestNextAttemptAt;
	}


	public function getDatetimeInserted(): \DateTimeInterface
	{
		return $this->datetimeInserted;
	}


	public function getDatetimeSent(): ?\DateTimeInterface
	{
		return $this->datetimeSent;
	}


	public function setDatetimeSent(?\DateTimeInterface $datetimeSent): void
	{
		$this->datetimeSent = $datetimeSent;
	}
}
