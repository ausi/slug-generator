<?php

declare(strict_types=1);

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ausi\SlugGenerator;

/**
 * Slug generator options.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 *
 * @implements \IteratorAggregate<string,mixed>
 */
class SlugOptions implements \IteratorAggregate
{
	/**
	 * @var string
	 */
	private $delimiter = '-';

	/**
	 * @var string
	 */
	private $validChars = 'a-z0-9';

	/**
	 * @var string
	 */
	private $ignoreChars = '\p{Mn}\p{Lm}';

	/**
	 * @var string
	 */
	private $locale = '';

	/**
	 * @var array<string>
	 */
	private $transforms = [
		'Upper',
		'Lower',
		'Latn',
		'ASCII',
		// Upper and Lower need to be applied again after the other transforms
		'Upper',
		'Lower',
	];

	/**
	 * @var array<string,null>
	 */
	private $setOptions = [];

	/**
	 * @param iterable<string,mixed> $options See the setter methods for available options
	 */
	public function __construct(iterable $options = [])
	{
		foreach ($options as $option => $value) {
			$this->assertOptionName($option);
			$this->{'set'.ucfirst($option)}($value);
		}
	}

	/**
	 * Get an iterator for all options that have been explicitly set.
	 *
	 * {@inheritdoc}
	 *
	 * @return \Traversable<string,mixed>
	 */
	public function getIterator(): \Traversable
	{
		$options = [];

		foreach (array_keys($this->setOptions) as $option) {
			$options[$option] = $this->{'get'.ucfirst($option)}();
		}

		return new \ArrayIterator($options);
	}

	/**
	 * Merge the options with and return a new options object.
	 *
	 * @param iterable<string,mixed> $options SlugOptions object or options array
	 *
	 * @return static
	 */
	public function merge(iterable $options): self
	{
		$merged = clone $this;

		foreach ($options as $option => $value) {
			$this->assertOptionName($option);
			$merged->{'set'.ucfirst($option)}($value);
		}

		return $merged;
	}

	/**
	 * @param string $delimiter Delimiter that should be used between words
	 *
	 * @return static
	 */
	public function setDelimiter(string $delimiter): self
	{
		$this->delimiter = $delimiter;
		$this->setOptions['delimiter'] = null;

		return $this;
	}

	public function getDelimiter(): string
	{
		return $this->delimiter;
	}

	/**
	 * @param string $chars Character range for allowed characters
	 *                      in the form of a regular expression character set,
	 *                      e.g. `abc`, `a-z0-9` or `\p{Ll}\-_`
	 *
	 * @return static
	 */
	public function setValidChars(string $chars): self
	{
		$this->assertCharacterClass($chars);

		$this->validChars = $chars;
		$this->setOptions['validChars'] = null;

		return $this;
	}

	public function getValidChars(): string
	{
		return $this->validChars;
	}

	/**
	 * @param string $chars Range of characters that get ignored
	 *                      in the form of a regular expression character set,
	 *                      e.g. `abc`, `a-z0-9` or `\p{Ll}\-_`
	 *
	 * @return static
	 */
	public function setIgnoreChars(string $chars): self
	{
		$this->assertCharacterClass($chars);

		$this->ignoreChars = $chars;
		$this->setOptions['ignoreChars'] = null;

		return $this;
	}

	public function getIgnoreChars(): string
	{
		return $this->ignoreChars;
	}

	/**
	 * @param string $locale Locale string that should be used for transforms
	 *                       e.g. `de` or `en_US_Latn`
	 *
	 * @return static
	 */
	public function setLocale(string $locale): self
	{
		if ($locale === '') {
			$this->locale = $locale;
		} else {
			if (!\Locale::parseLocale($locale)) {
				throw new \InvalidArgumentException(sprintf('Invalid locale "%s"', $locale));
			}
			$this->locale = \Locale::canonicalize($locale);
		}

		$this->setOptions['locale'] = null;

		return $this;
	}

	public function getLocale(): string
	{
		return $this->locale;
	}

	/**
	 * @param string $transform Rule or ruleset to be used by the Transliterator,
	 *                          like `Lower`, `ASCII` or `a > b; c > d`
	 * @param bool   $top       If it should be applied before existing transforms
	 *
	 * @return static
	 */
	public function addTransform(string $transform, bool $top = true): self
	{
		$this->assertTransform($transform);

		if ($top) {
			array_unshift($this->transforms, $transform);
		} else {
			$this->transforms[] = $transform;
		}

		$this->setOptions['transforms'] = null;

		return $this;
	}

	/**
	 * @param iterable<string> $transforms List of rules or rulesets to be used by the Transliterator,
	 *                                     like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setTransforms(iterable $transforms): self
	{
		$this->transforms = [];

		foreach ($transforms as $transform) {
			$this->assertTransform($transform);
			$this->addTransform($transform, false);
		}

		$this->setOptions['transforms'] = null;

		return $this;
	}

	/**
	 * @return array<string>
	 */
	public function getTransforms(): array
	{
		return $this->transforms;
	}

	/**
	 * Add transforms before existing ones.
	 *
	 * @param iterable<string> $transforms List of rules or rulesets to be used by the Transliterator,
	 *                                     like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setPreTransforms(iterable $transforms): self
	{
		if (!\is_array($transforms)) {
			$transforms = iterator_to_array($transforms, false);
		}

		foreach (array_reverse($transforms) as $transform) {
			$this->assertTransform($transform);
			$this->addTransform($transform);
		}

		return $this;
	}

	/**
	 * Add transforms after existing ones.
	 *
	 * @param iterable<string> $transforms List of rules or rulesets to be used by the Transliterator,
	 *                                     like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setPostTransforms(iterable $transforms): self
	{
		foreach ($transforms as $transform) {
			$this->assertTransform($transform);
			$this->addTransform($transform, false);
		}

		return $this;
	}

	/**
	 * @throws \InvalidArgumentException If it’s an invalid option name
	 */
	private function assertOptionName(string $option): void
	{
		static $validOptions = [
			'delimiter',
			'validChars',
			'ignoreChars',
			'locale',
			'transforms',
			'preTransforms',
			'postTransforms',
		];

		if (!\in_array($option, $validOptions, true)) {
			throw new \InvalidArgumentException(sprintf('Unknown option "%s"', $option));
		}
	}

	/**
	 * @throws \InvalidArgumentException If it’s an invalid regex character class
	 */
	private function assertCharacterClass(string $chars): void
	{
		SlugGenerator::checkPcreSupport();

		if ($chars !== '' && ($chars[0] === '^' || @preg_match('(^['.$chars.']?$)u', '') !== 1)) {
			throw new \InvalidArgumentException(sprintf('Invalid regular expression character class "%s"', $chars));
		}
	}

	/**
	 * @phpstan-param mixed $transform
	 *
	 * @throws \InvalidArgumentException If it’s an invalid transform
	 */
	private function assertTransform($transform): void
	{
		if (!\is_string($transform)) {
			throw new \InvalidArgumentException(sprintf('Transform must be of the type string, %s given', \gettype($transform)));
		}

		if ($transform === '') {
			throw new \InvalidArgumentException('Transform must not be empty');
		}
	}
}
