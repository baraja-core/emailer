<?php

declare(strict_types=1);

namespace Baraja\Emailer;


use Nette\Utils\Strings;

final class Route
{
	public const
		DefaultPresenter = 'Homepage',
		DefaultAction = 'default',
		DefaultRoute = 'Homepage:default';

	private const Pattern = '/^(?:(?<module>[A-Za-z]*):)?(?<presenter>[A-Za-z]*):(?<action>[A-Za-z]+)(?<params>\,*?.*?)$/';

	private ?string $module;

	private string $presenterName;

	private string $actionName;

	private ?string $id;

	/** @var array<string, string> */
	private array $params;


	/**
	 * @param array<string, string> $params
	 */
	public function __construct(
		string $module = null,
		string $presenter = self::DefaultPresenter,
		string $action = self::DefaultAction,
		string $id = null,
		array $params = [],
	) {
		$this->module = $module !== '' ? $module : null;
		$presenterName = trim(Strings::firstUpper($presenter !== '' ? $presenter : self::DefaultPresenter), '/');
		$actionName = trim(Strings::firstLower($action !== '' ? $action : self::DefaultAction), '/');
		$this->presenterName = $presenterName !== '' ? $presenterName : self::DefaultPresenter;
		$this->actionName = $actionName !== '' ? $actionName : self::DefaultAction;
		$this->id = $id !== '' && $id !== null ? trim($id, '/') : null;
		$this->params = $params;
	}


	/**
	 * @param string $pattern in format "[Module:]Presenter:action, id => 123, param => value, foo => bar"
	 */
	public static function createByPattern(string $pattern): self
	{
		if (preg_match(self::Pattern, trim($pattern, ':'), $patternParser) !== 1) {
			throw new \InvalidArgumentException(sprintf('Invalid link "%s". Did you mean format "Presenter:action" or "Module:Presenter:action"?', htmlspecialchars($pattern)));
		}

		$id = null;
		$params = [];
		foreach (explode(',', trim($patternParser['params'], ', ')) as $param) {
			if (preg_match('/^(?<key>[\'"]?\w+[\'"]?)\s*=>\s*(?<value>.*)$/', trim($param), $paramParser) === 1) {
				$paramKey = trim($paramParser['key'], '\'"');
				if ($paramKey === 'id') {
					$id = $paramParser['value'];
				}

				$params[$paramKey] = \is_string($paramParser['value'])
					? trim($paramParser['value'], '\'"')
					: $paramParser['value'];
			}
		}

		return new self(
			$patternParser['module'] ?? null,
			$patternParser['presenter'],
			$patternParser['action'],
			$id,
			$params,
		);
	}


	public function __toString(): string
	{
		return $this->toString();
	}


	/**
	 * Return formats:
	 *    Presenter:action
	 *    Presenter:action, id => 123
	 *    Presenter:action, id => 123, param => value, foo => bar
	 */
	public function toString(): string
	{
		$returnParams = array_merge(
			$this->params,
			$this->id !== null ? ['id' => $this->id] : [],
		);

		$return = Strings::firstUpper($this->presenterName) . ':' . $this->actionName;
		foreach ($returnParams as $paramKey => $paramValue) {
			$return .= ', ' . $paramKey . ' => ' . $paramValue;
		}

		return $return;
	}


	public function getModule(): ?string
	{
		return $this->module;
	}


	public function getPresenterName(bool $withModule = true): string
	{
		if ($withModule === true) {
			$module = $this->module === null || trim($this->module) === ''
				? 'Front:'
				: $this->module . ':';

			return $module . $this->presenterName;
		}

		return $this->presenterName;
	}


	public function getActionName(): string
	{
		return $this->actionName;
	}


	public function isDefault(): bool
	{
		return $this->getActionName() === self::DefaultAction;
	}


	/**
	 * @return array<string, int|mixed>
	 */
	public function getParams(): array
	{
		$return = [];
		foreach ($this->params as $key => $value) {
			$return[$key] = $this->isNumericInt($value)
				? (int) $value
				: $value;
		}

		return $return;
	}


	private function isNumericInt(mixed $value): bool
	{
		return is_int($value) || (is_string($value) && preg_match('#^-?[\d]+\z#', $value) === 1);
	}
}
