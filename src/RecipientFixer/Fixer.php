<?php

declare(strict_types=1);

namespace Baraja\Emailer\RecipientFixer;


interface Fixer
{
	/** Replace broken domain name in e-mail address by table. */
	public function fix(string $email): string;
}
