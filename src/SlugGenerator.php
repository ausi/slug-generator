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
 * Slug generator.
 *
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugGenerator
{
	/**
	 * @var SlugOptions
	 */
	private $options;

	/**
	 * @var \Transliterator[]
	 */
	private $transliterators = [];

	/**
	 * @param iterable $options SlugOptions object or options array
	 */
	public function __construct(iterable $options = [])
	{
		static::checkPcreSupport();

		if (!$options instanceof SlugOptions) {
			$options = new SlugOptions($options);
		}

		$this->options = $options;
	}

	/**
	 * @throws \RuntimeException If PCRE unicode support is missing
	 *
	 * @internal
	 */
	public static function checkPcreSupport(): void
	{
		static $supported = null;

		if ($supported === null) {
			$supported = @preg_match('/\pL/u', 'a') === 1;
		}

		if (!$supported) {
			throw new \RuntimeException(sprintf('Missing PCRE unicode support'));
		}
	}

	/**
	 * Generate a slug from the specified text.
	 *
	 * @param string   $text
	 * @param iterable $options SlugOptions object or options array
	 *
	 * @return string
	 */
	public function generate(string $text, iterable $options = []): string
	{
		if (preg_match('//u', $text) !== 1) {
			throw new \InvalidArgumentException('Text is invalid UTF-8');
		}

		$options = $this->options->merge($options);

		if ($options->getValidChars() === '') {
			return '';
		}

		$text = \Normalizer::normalize($text, \Normalizer::FORM_C);
		$text = $this->removeIgnored($text, $options->getIgnoreChars());
		$text = $this->transform($text, $options->getValidChars(), $options->getTransforms(), $options->getLocale());
		$text = $this->removeIgnored($text, $options->getIgnoreChars());
		$text = $this->replaceWithDelimiter($text, $options->getValidChars(), $options->getDelimiter());

		return $text;
	}

	/**
	 * Remove ignored characters from text.
	 *
	 * @param string $text
	 * @param string $ignore
	 *
	 * @return string
	 */
	private function removeIgnored(string $text, string $ignore): string
	{
		if ($ignore === '') {
			return $text;
		}

		return preg_replace('(['.$ignore.'])us', '', $text);
	}

	/**
	 * Replace all invalid characters with a delimiter
	 * and strip the delimiter from the beginning and the end.
	 *
	 * @param string $text
	 * @param string $valid
	 * @param string $delimiter
	 *
	 * @return string
	 */
	private function replaceWithDelimiter(string $text, string $valid, string $delimiter): string
	{
		$quoted = preg_quote($delimiter);

		// Replace all invalid characters with a single delimiter
		$text = preg_replace(
			'((?:[^'.$valid.']|'.$quoted.')+)us',
			$delimiter,
			$text
		);

		// Remove delimiters from the beginning and the end
		$text = preg_replace('(^(?:'.$quoted.')+|(?:'.$quoted.')+$)us', '', $text);

		return $text;
	}

	/**
	 * Apply all transforms with the specified locale
	 * to the invalid parts of the text.
	 *
	 * @param string   $text
	 * @param string   $valid
	 * @param iterable $transforms
	 * @param string   $locale
	 *
	 * @return string
	 */
	private function transform(string $text, string $valid, iterable $transforms, string $locale): string
	{
		$regexRegular = '([^'.$valid.']+)us';
		$regexCase = $this->createCaseRegex($valid);

		foreach ($transforms as $transform) {
			$regex = ($transform === 'Lower' || $transform === 'Upper') ? $regexCase : $regexRegular;
			if ($locale) {
				$text = $this->applyTransformRule($text, $transform, $locale, $regex);
			}
			$text = $this->applyTransformRule($text, $transform, '', $regex);
		}

		return $text;
	}

	/**
	 * Create a regular expression that matches all characters that are invalid
	 * but whose lower/upper-case counterparts are valid.
	 *
	 * @param string $valid
	 *
	 * @return string
	 */
	private function createCaseRegex(string $valid): string
	{
		$insensitive = $valid;

		// Fix case insensitive matching of turkish “I” characters
		if (preg_match('(['.$valid.'])us', 'İı')) {
			$insensitive .= 'İı';
		}

		$insensitive = preg_replace_callback('(\\\\([pP])\{L([lu])\})s', function ($match) {
			return '\\'.$match[1].'{L'.($match[2] === 'l' ? 'u' : 'l').'}';
		}, $insensitive);

		return '((?:(?=(?i)['.$insensitive.'])[^'.$valid.'])+)us';
	}

	/**
	 * Apply a transform rule with the specified locale
	 * to the parts that match the regular expression.
	 *
	 * @param string $text
	 * @param string $rule
	 * @param string $locale
	 * @param string $regex
	 *
	 * @return string
	 */
	private function applyTransformRule(string $text, string $rule, string $locale, string $regex): string
	{
		$transliterator = $this->getTransliterator($rule, $locale);
		$newText = '';
		$offset = 0;

		foreach ($this->getRanges($text, $regex) as $range) {
			$newText .= substr($text, $offset, $range[0] - $offset);
			$newText .= $this->transformWithContext($transliterator, $text, $range[0], $range[1]);
			$offset = $range[0] + $range[1];
		}

		$newText .= substr($text, $offset);

		return $newText;
	}

	/**
	 * Transform the text at the specified position
	 * and use a one character context if possible.
	 *
	 * `Transliterator::transliterate()` doesn’t yet support context parameters
	 * of the underlying ICU implementation.
	 * Because of that, we add the context before the transform
	 * and check afterwards that the context didn’t change.
	 *
	 * @param \Transliterator $transliterator
	 * @param string          $text
	 * @param int             $index
	 * @param int             $length
	 *
	 * @return string
	 */
	private function transformWithContext(\Transliterator $transliterator, string $text, int $index, int $length): string
	{
		$left = mb_substr(substr($text, 0, $index), -1, null, 'UTF-8');
		$right = mb_substr(substr($text, $index + $length), 0, 1, 'UTF-8');

		$leftLength = strlen($left);
		$rightLength = strlen($right);

		$text = substr($text, $index, $length);

		$transformed = $transliterator->transliterate($left.$text.$right);

		if (
			(!$leftLength || strncmp($transformed, $left, $leftLength) === 0)
			&& (!$rightLength || substr_compare($transformed, $right, -$rightLength) === 0)
		) {
			return substr($transformed, $leftLength, $rightLength ? -$rightLength : strlen($transformed));
		} else {
			return $transliterator->transliterate($text);
		}
	}

	/**
	 * Get the Transliterator for the specified transform rule and locale.
	 *
	 * @param string $rule
	 * @param string $locale
	 *
	 * @return \Transliterator
	 */
	private function getTransliterator(string $rule, string $locale): \Transliterator
	{
		$key = $rule.'|'.$locale;

		if (!isset($this->transliterators[$key])) {
			$this->transliterators[$key] = $this->findMatchingTransliterator($rule, $locale);
		}

		return $this->transliterators[$key];
	}

	/**
	 * Find the best matching Transliterator
	 * for the specified transform rule and locale.
	 *
	 * @param string $rule
	 * @param string $locale
	 *
	 * @return \Transliterator
	 */
	private function findMatchingTransliterator(string $rule, string $locale): \Transliterator
	{
		$rule = $this->fixTransliteratorRule($rule, $locale);

		$candidates = [
			'Latin-'.$rule,
			$rule,
		];

		if ($locale) {
			array_unshift(
				$candidates,
				$locale.'-'.$rule,
				\Locale::getPrimaryLanguage($locale).'-'.$rule
			);
		}

		foreach ($candidates as $candidate) {
			if ($transliterator = \Transliterator::create($candidate)) {
				return $transliterator;
			}
		}

		if ($transliterator = \Transliterator::createFromRules($rule)) {
			return $transliterator;
		}

		throw new \InvalidArgumentException(
			sprintf('No Transliterator transform rule found for "%s" with locale "%s".', $rule, $locale)
		);
	}

	/**
	 * Apply fixes to a transform rule for older versions of the Intl extension.
	 *
	 * @param string $rule
	 * @param string $locale
	 *
	 * @return string
	 */
	private function fixTransliteratorRule(string $rule, string $locale): string
	{
		static $deAsciiFixNeeded;

		if ($deAsciiFixNeeded === null) {
			$deAsciiFixNeeded = !in_array('de-ASCII', \Transliterator::listIDs(), true);
		}

		// Add the de-ASCII transform if a CLDR version lower than 32.0 is used.
		if ($deAsciiFixNeeded && $rule === 'ASCII' && $locale && \Locale::getPrimaryLanguage($locale) === 'de') {
			return
				'$AE = [Ä {A \u0308}];'
				.'$OE = [Ö {O \u0308}];'
				.'$UE = [Ü {U \u0308}];'
				.'[ä {a \u0308}] > ae;'
				.'[ö {o \u0308}] > oe;'
				.'[ü {u \u0308}] > ue;'
				.'$AE } [:Lowercase:] > Ae;'
				.'$OE } [:Lowercase:] > Oe;'
				.'$UE } [:Lowercase:] > Ue;'
				.'$AE > AE;'
				.'$OE > OE;'
				.'$UE > UE;'
				.'::Latin-ASCII;' // Any-ASCII is not available in older CLDR versions
			;
		}

		return $rule;
	}

	/**
	 * Get all matching ranges.
	 *
	 * @param string $text
	 * @param string $regex
	 *
	 * @return array Array of range arrays, each consisting of index and length
	 */
	private function getRanges(string $text, string $regex): array
	{
		preg_match_all($regex, $text, $matches, PREG_OFFSET_CAPTURE);

		$ranges = array_map(function ($match) {
			return [$match[1], strlen($match[0])];
		}, $matches[0]);

		return $ranges;
	}
}
