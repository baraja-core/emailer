<?php

declare(strict_types=1);

namespace Baraja\Emailer;


interface EmailerAccessor
{
	public function get(): Emailer;
}
