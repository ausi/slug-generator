<?php

/*
 * This file is part of the ausi/slug-generator package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ausi\SlugGenerator;

/**
 * Slug generator options.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
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
	private $valid = 'a-z0-9';

	/**
	 * @var string
	 */
	private $ignore = '\p{Mn}\p{Lm}';

	/**
	 * @var string
	 */
	private $locale = '';

	/**
	 * @var string[]
	 */
	private $transforms = [
		'Upper',
		'Lower',
		'Latn',
		'ASCII',
		'Upper',
		'Lower',
	];

	/**
	 * @var array
	 */
	private $setOptions = [];

	/**
	 * @param iterable $options See the setter methods for available options
	 */
	public function __construct(iterable $options = [])
	{
		foreach ($options as $option => $value) {
			$this->{'set'.ucfirst($option)}($value);
		}
	}

	/**
	 * Get an iterator for all options that have been explicitly set.
	 *
	 * {@inheritdoc}
	 */
	public function getIterator(): \Traversable
	{
		$options = [];

		foreach ($this->setOptions as $option => $value) {
			$options[$option] = $this->{'get'.ucfirst($option)}();
		}

		return new \ArrayIterator($options);
	}

	/**
	 * Merge the options with and return a new options object.
	 *
	 * @param iterable $options SlugOptions object or options array
	 *
	 * @return static
	 */
	public function merge(iterable $options): self
	{
		$merged = clone $this;

		foreach ($options as $option => $value) {
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

	/**
	 * @return string
	 */
	public function getDelimiter(): string
	{
		return $this->delimiter;
	}

	/**
	 * @param string $valid Character range for allowed characters
	 *                      in the form of a regular expression character set,
	 *                      e.g. `abc`, `a-z0-9` or `\p{Ll}\-_`
	 *
	 * @return static
	 */
	public function setValid(string $valid): self
	{
		$this->assertCharacterClass($valid);

		$this->valid = $valid;
		$this->setOptions['valid'] = null;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getValid(): string
	{
		return $this->valid;
	}

	/**
	 * @param string $ignore Range of characters that get ignored
	 *                       in the form of a regular expression character set,
	 *                       e.g. `abc`, `a-z0-9` or `\p{Ll}\-_`
	 *
	 * @return static
	 */
	public function setIgnore(string $ignore): self
	{
		$this->assertCharacterClass($ignore);

		$this->ignore = $ignore;
		$this->setOptions['ignore'] = null;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getIgnore(): string
	{
		return $this->ignore;
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

	/**
	 * @return string
	 */
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
		if ($transform === '') {
			throw new \InvalidArgumentException('Transform must not be empty');
		}

		if ($top) {
			array_unshift($this->transforms, $transform);
		} else {
			$this->transforms[] = $transform;
		}

		$this->setOptions['transforms'] = null;

		return $this;
	}

	/**
	 * @param iterable $transforms List of rules or rulesets to be used by the Transliterator,
	 *                             like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setTransforms(iterable $transforms): self
	{
		$this->transforms = [];

		foreach ($transforms as $transform) {
			if (!is_string($transform)) {
				throw new \InvalidArgumentException(sprintf('Transform must be of the type string, %s given', gettype($transform)));
			}

			$this->addTransform($transform, false);
		}

		$this->setOptions['transforms'] = null;

		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getTransforms(): array
	{
		return $this->transforms;
	}

	/**
	 * Add transforms before existing ones.
	 *
	 * @param iterable $transforms List of rules or rulesets to be used by the Transliterator,
	 *                             like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setPreTransforms(iterable $transforms): self
	{
		if (!is_array($transforms)) {
			$transforms = iterator_to_array($transforms, false);
		}

		return $this->setTransforms(array_merge($transforms, $this->transforms));
	}

	/**
	 * Add transforms after existing ones.
	 *
	 * @param iterable $transforms List of rules or rulesets to be used by the Transliterator,
	 *                             like `Lower`, `ASCII` or `a > b; c > d`
	 *
	 * @return static
	 */
	public function setPostTransforms(iterable $transforms): self
	{
		if (!is_array($transforms)) {
			$transforms = iterator_to_array($transforms, false);
		}

		return $this->setTransforms(array_merge($this->transforms, $transforms));
	}

	/**
	 * @param string $chars
	 *
	 * @throws \InvalidArgumentException If it’s an invalid regex character class
	 */
	private function assertCharacterClass(string $chars): void
	{
		SlugGenerator::checkPcreSupport();

		if ($chars !== '' && ($chars[0] === '^' || @preg_match('(^['.$chars.']?$)u', '') !== 1)) {
			throw new \InvalidArgumentException(sprintf('Invalid regular expression character class "%s"', $chars));
		}
	}
}
