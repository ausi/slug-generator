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

namespace Ausi\SlugGenerator\Tests;

use Ausi\SlugGenerator\SlugOptions;
use PHPUnit\Framework\TestCase;

/**
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugOptionsTest extends TestCase
{
	public function testInstantiation(): void
	{
		$this->assertInstanceOf(SlugOptions::class, new SlugOptions);
		$this->assertInstanceOf(SlugOptions::class, new SlugOptions([]));
		$this->assertInstanceOf(SlugOptions::class, (new SlugOptions)->merge([]));
	}

	public function testMerge(): void
	{
		$options = (new SlugOptions)->merge(new SlugOptions(['delimiter' => 'x']));

		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('a-z0-9', $options->getValidChars());

		$options2 = $options->merge(new SlugOptions(['validChars' => 'x']));
		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('a-z0-9', $options->getValidChars());
		$this->assertSame('x', $options2->getDelimiter());
		$this->assertSame('x', $options2->getValidChars());
	}

	public function testGetIterator(): void
	{
		$options = new SlugOptions;
		$this->assertSame([], iterator_to_array($options));

		$options = new SlugOptions(['delimiter' => 'x']);
		$this->assertSame(['delimiter' => 'x'], iterator_to_array($options));

		$options->setDelimiter('-');
		$this->assertSame(['delimiter' => '-'], iterator_to_array($options));

		$options->setValidChars('x');
		$this->assertSame(['delimiter' => '-', 'validChars' => 'x'], iterator_to_array($options));
	}

	public function testSetDelimiter(): void
	{
		$options = new SlugOptions;
		$this->assertSame('-', $options->getDelimiter());

		$options = new SlugOptions(['delimiter' => 'x']);
		$this->assertSame('x', $options->getDelimiter());
		$this->assertSame('', $options->setDelimiter('')->getDelimiter());
		$this->assertSame('x', $options->setDelimiter('x')->getDelimiter());
		$this->assertSame('xx', $options->setDelimiter('xx')->getDelimiter());
	}

	public function testSetValidChars(): void
	{
		$options = new SlugOptions;
		$this->assertSame('a-z0-9', $options->getValidChars());

		$options = new SlugOptions(['validChars' => 'x']);
		$this->assertSame('x', $options->getValidChars());
		$this->assertSame('', $options->setValidChars('')->getValidChars());
		$this->assertSame('a', $options->setValidChars('a')->getValidChars());
		$this->assertSame('a-c', $options->setValidChars('a-c')->getValidChars());
		$this->assertSame('\p{Ll}', $options->setValidChars('\p{Ll}')->getValidChars());
		$this->assertSame('/', $options->setValidChars('/')->getValidChars());
		$this->assertSame('\\\\', $options->setValidChars('\\\\')->getValidChars());
		$this->assertSame('{}*?.\\]', $options->setValidChars('{}*?.\\]')->getValidChars());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 */
	public function testSetValidCharsThrows(string $valid): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('("'.preg_quote($valid).'")');

		$options->setValidChars($valid);
	}

	public function testSetIgnoreChars(): void
	{
		$options = new SlugOptions;
		$this->assertSame('\p{Mn}\p{Lm}', $options->getIgnoreChars());

		$options = new SlugOptions(['ignoreChars' => 'x']);
		$this->assertSame('x', $options->getIgnoreChars());
		$this->assertSame('', $options->setIgnoreChars('')->getIgnoreChars());
		$this->assertSame('a', $options->setIgnoreChars('a')->getIgnoreChars());
		$this->assertSame('a-c', $options->setIgnoreChars('a-c')->getIgnoreChars());
		$this->assertSame('\p{Ll}', $options->setIgnoreChars('\p{Ll}')->getIgnoreChars());
		$this->assertSame('/', $options->setIgnoreChars('/')->getIgnoreChars());
		$this->assertSame('\\\\', $options->setIgnoreChars('\\\\')->getIgnoreChars());
		$this->assertSame('{}*?.\\]', $options->setIgnoreChars('{}*?.\\]')->getIgnoreChars());
	}

	/**
	 * @dataProvider getInvalidCharacterClasses
	 */
	public function testSetIgnoreCharsThrows(string $ignore): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('("'.preg_quote($ignore).'")');

		$options->setIgnoreChars($ignore);
	}

	/**
	 * @return array<array<string>>
	 */
	public function getInvalidCharacterClasses(): array
	{
		return [
			['^a'],
			['\\'],
			['\p'],
			['\p{'],
			['a]'],
		];
	}

	public function testSetLocale(): void
	{
		$options = new SlugOptions;
		$this->assertSame('', $options->getLocale());

		$options = new SlugOptions(['locale' => 'en']);
		$this->assertSame('en', $options->getLocale());
		$this->assertSame('', $options->setLocale('')->getLocale());
		$this->assertSame('en', $options->setLocale('en')->getLocale());
		$this->assertSame('en_US', $options->setLocale('en_US')->getLocale());
		$this->assertSame('en_US', $options->setLocale('en-us')->getLocale());
		$this->assertSame('en_US_LATN_XXXX', $options->setLocale('en-us-latn-xxxx')->getLocale());
	}

	/**
	 * @dataProvider getSetLocaleThrows
	 */
	public function testSetLocaleThrows(string $locale): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('("'.preg_quote($locale).'")');

		$options->setLocale($locale);
	}

	/**
	 * @return array<array<string>>
	 */
	public function getSetLocaleThrows()
	{
		return [
			["\0"],
			['-'],
			[str_repeat('a', INTL_MAX_LOCALE_LEN + 1)],
		];
	}

	public function testSetTransforms(): void
	{
		$options = new SlugOptions;
		$this->assertSame(['Upper', 'Lower', 'Latn', 'ASCII', 'Upper', 'Lower'], $options->getTransforms());

		$options = new SlugOptions(['transforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Lower'], $options->setTransforms(['Lower'])->getTransforms());
		$this->assertSame(['a > b', 'c > d'], $options->setTransforms(['a > b', 'c > d'])->getTransforms());
		$this->assertSame(['a > b; ::Lower();'], $options->setTransforms(['a > b; ::Lower();'])->getTransforms());
		$this->assertSame(['Lower'], $options->setTransforms(new \ArrayIterator(['Lower']))->getTransforms());
		$this->assertSame([], $options->setTransforms([])->getTransforms());

		$this->assertSame(['Lower'], $options->addTransform('Lower')->getTransforms());
		$this->assertSame(['Upper', 'Lower'], $options->addTransform('Upper')->getTransforms());
		$this->assertSame(['ASCII', 'Upper', 'Lower'], $options->addTransform('ASCII', true)->getTransforms());
		$this->assertSame(['ASCII', 'Upper', 'Lower', 'Latn'], $options->addTransform('Latn', false)->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @phpstan-param mixed $transform
	 */
	public function testSetTransformsThrows($transform): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setTransforms([$transform]);
	}

	public function testSetPreTransforms(): void
	{
		$options = new SlugOptions(['transforms' => [], 'preTransforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Lower', 'Upper'], $options->setPreTransforms(['Lower'])->getTransforms());

		// Test iterators with duplicate keys
		$iterator = new \AppendIterator;
		$iterator->append(new \ArrayIterator(['key1' => 'a > b']));
		$iterator->append(new \ArrayIterator(['key1' => 'b > c']));

		$this->assertSame(['a > b', 'b > c', 'Lower', 'Upper'], $options->setPreTransforms($iterator)->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @phpstan-param mixed $transform
	 */
	public function testSetPreTransformsThrows($transform): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setPreTransforms([$transform]);
	}

	public function testSetPostTransforms(): void
	{
		$options = new SlugOptions(['transforms' => [], 'postTransforms' => ['Upper']]);
		$this->assertSame(['Upper'], $options->getTransforms());
		$this->assertSame(['Upper', 'Lower'], $options->setPostTransforms(['Lower'])->getTransforms());

		// Test iterators with duplicate keys
		$iterator = new \AppendIterator;
		$iterator->append(new \ArrayIterator(['key1' => 'a > b']));
		$iterator->append(new \ArrayIterator(['key1' => 'b > c']));

		$this->assertSame(['Upper', 'Lower', 'a > b', 'b > c'], $options->setPostTransforms($iterator)->getTransforms());
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @phpstan-param mixed $transform
	 */
	public function testSetPostTransformsThrows($transform): void
	{
		$options = new SlugOptions;

		$this->expectException(\InvalidArgumentException::class);

		$options->setPreTransforms([$transform]);
	}

	/**
	 * @dataProvider getAddTransformThrows
	 *
	 * @phpstan-param mixed $transform
	 *
	 * @param class-string<\Throwable> $expectedException
	 */
	public function testAddTransformThrows($transform, string $expectedException): void
	{
		$options = new SlugOptions(['transforms' => []]);

		if ($expectedException) {
			$this->expectException($expectedException);
		}

		$this->assertSame([$transform], $options->addTransform($transform)->getTransforms());
	}

	/**
	 * @return array<array>
	 */
	public function getAddTransformThrows()
	{
		return [
			['', \InvalidArgumentException::class],
			[123, \TypeError::class],
			[[], \TypeError::class],
			[new \stdClass, \TypeError::class],
		];
	}

	public function testUnknownOptionThrows(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('(unknown.*"foo")i');

		new SlugOptions(['foo' => 'bar']);
	}

	/**
	 * @psalm-suppress UndefinedMethod
	 */
	private function expectExceptionMatches(string $regularExpression): void
	{
		if (method_exists($this, 'expectExceptionMessageMatches')) {
			$this->expectExceptionMessageMatches($regularExpression);
		} else {
			// PHPUnit 7 compat
			/** @phpstan-ignore-next-line */
			$this->expectExceptionMessageRegExp($regularExpression);
		}
	}
}
