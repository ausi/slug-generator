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

use Ausi\SlugGenerator\SlugGenerator;
use Ausi\SlugGenerator\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugGeneratorTest extends TestCase
{
	public function testInstantiation(): void
	{
		$this->assertInstanceOf(SlugGenerator::class, new SlugGenerator);
		$this->assertInstanceOf(SlugGenerator::class, new SlugGenerator([]));
		$this->assertInstanceOf(SlugGeneratorInterface::class, new SlugGenerator);
	}

	/**
	 * @param array<string,mixed> $options
	 * @dataProvider getGenerate
	 */
	public function testGenerate(string $source, string $expected, array $options = [], bool $skip = false): void
	{
		if ($skip) {
			$this->markTestSkipped();
		}

		$generator = new SlugGenerator($options);
		$this->assertSame($expected, $generator->generate($source));

		$generator = new SlugGenerator;
		$this->assertSame($expected, $generator->generate($source, $options));
	}

	/**
	 * @param array<string,mixed> $options
	 * @dataProvider getGenerate
	 */
	public function testGenerateWithIntlErrors(string $source, string $expected, array $options = [], bool $skip = false): void
	{
		$errorLevel = ini_get('intl.error_level');
		$useExceptions = ini_get('intl.use_exceptions');

		try {
			ini_set('intl.error_level', (string) E_WARNING);
			$this->testGenerate($source, $expected, $options, $skip);
			$this->assertSame((string) E_WARNING, ini_get('intl.error_level'));

			ini_set('intl.error_level', (string) E_ERROR);
			$this->testGenerate($source, $expected, $options, $skip);
			$this->assertSame((string) E_ERROR, ini_get('intl.error_level'));

			ini_set('intl.use_exceptions', '1');
			$this->testGenerate($source, $expected, $options, $skip);
			$this->assertSame('1', ini_get('intl.use_exceptions'));
		} finally {
			ini_set('intl.error_level', (string) $errorLevel);
			ini_set('intl.use_exceptions', (string) $useExceptions);
		}
	}

	/**
	 * @return array<array>
	 */
	public function getGenerate(): array
	{
		return [
			['föobär', 'foobar'],
			[
				'föobär',
				'foeobaer',
				['locale' => 'de'],
			],
			[
				'föobär',
				'foobar',
				['locale' => 'en_US'],
			],
			[
				'Ö Äpfel-Fuß',
				'OE-Aepfel-Fuss',
				//'OE-AEpfel-Fuss',
				['validChars' => 'a-zA-Z', 'locale' => 'de'],
			],
			[
				'Ö Ä Ü ẞ ÖX ÄX ÜX ẞX Öx Äx Üx ẞx Öö Ää Üü ẞß',
				'OE-AE-UE-SS-OEX-AEX-UEX-SSX-Oex-Aex-Uex-SSx-Oeoe-Aeae-Ueue-SSss',
				//'OE-AE-UE-SS-OEX-AEX-UEX-SSX-OEx-AEx-UEx-SSx-Oeoe-Aeae-Ueue-SSss',
				['validChars' => 'a-zA-Z', 'locale' => 'de'],
			],
			[
				"O\u{308} A\u{308} U\u{308} O\u{308}X A\u{308}X U\u{308}X O\u{308}x A\u{308}x U\u{308}x O\u{308}o\u{308} A\u{308}a\u{308} U\u{308}u\u{308}",
				'OE-AE-UE-OEX-AEX-UEX-Oex-Aex-Uex-Oeoe-Aeae-Ueue',
				//'OE-AE-UE-OEX-AEX-UEX-OEx-AEx-UEx-Oeoe-Aeae-Ueue',
				['validChars' => 'a-zA-Z', 'locale' => 'de'],
			],
			[
				'Ö Äpfel-Fuß',
				'ö-äpfel-fuß',
				['validChars' => 'a-zäöüß'],
			],
			[
				'ö-äpfel-fuß',
				'OE__AEPFEL__FUSS',
				['validChars' => 'A-Z', 'delimiter' => '__', 'locale' => 'de'],
			],
			['İNATÇI', 'inatci'],
			[
				'inatçı',
				'INATCI',
				['validChars' => 'A-Z'],
			],
			[
				'İNATÇI',
				'inatçı',
				[
					'validChars' => 'a-pr-vyzçğıöşü', // Turkish alphabet
					'locale' => 'tr',
				],
				version_compare(INTL_ICU_VERSION, '51.2', '<'),
			],
			[
				'inatçı',
				'İNATÇI',
				[
					'validChars' => 'A-PR-VYZÇĞİÖŞÜ', // Turkish alphabet
					'locale' => 'tr',
				],
				version_compare(INTL_ICU_VERSION, '51.2', '<'),
			],
			['Καλημέρα', 'kalemera'],
			[
				'Καλημέρα',
				'kalimera',
				['locale' => 'el'],
			],
			['國語', 'guo-yu'],
			['김, 국삼', 'gim-gugsam'],
			[
				'富士山',
				'fu-shi-shan',
				['locale' => 'ja'],
			],
			[
				'富士山',
				'fù-shì-shān',
				['validChars' => '\p{Latin}'],
			],
			[
				'Exämle <!-- % {{BR}} --> <a href="http://example.com">',
				'exämle-br-a-href-http-example-com',
				['validChars' => '\p{Ll}'],
			],
			[
				'Exämle <!-- % {{BR}} --> <a href="http://example.com">',
				'EXÄMLE-BR-A-HREF-HTTP-EXAMPLE-COM',
				['validChars' => '\p{Lu}'],
			],
			[
				'ǈ ǋ ǲ',
				'lj-nj-dz',
				['validChars' => '\p{Ll}'],
			],
			[
				'ǈ ǋ ǲ',
				'LJ-NJ-DZ',
				['validChars' => '\p{Lu}'],
			],
			[
				'ABC',
				'ac',
				['ignoreChars' => 'b'],
			],
			[
				'ABʹC',
				'abc',
			],
			[
				'ABʹC',
				'ab-c',
				['ignoreChars' => ''],
			],
			[
				'Don’t they\'re',
				'dont-theyre',
				['ignoreChars' => '’\''],
			],
			[
				'фильм',
				'film',
				['ignoreChars' => '\p{Mn}\p{Lm}\pP'],
			],
			['Україна', 'ukraina'],
			['０ １ ９ ⑽ ⒒ ¼ Ⅻ', '0-1-9-10-11-1-4-xii'],
			['Č Ć Ž Š Đ č ć ž š đ', 'c-c-z-s-d-c-c-z-s-d'],
			['Ą Č Ę Ė Į Š Ų Ū Ž ą č ę ė į š ų ū ž', 'a-c-e-e-i-s-u-u-z-a-c-e-e-i-s-u-u-z'],
			[
				'abc',
				'1b3',
				[
					'validChars' => 'b\d',
					'transforms' => ['a > 1; b > 1; c > 3;'],
				],
			],
			[
				'o ö',
				'o-x',
				['preTransforms' => ['ö > ä', 'ä > x']],
			],
			[
				'o ö',
				'o-o',
				['postTransforms' => ['ö > ä', 'ä > x']],
			],
			[
				'Damn 💩!!',
				'damn-chocolate-ice-cream',
				['preTransforms' => ['💩 > Chocolate \u0020 Ice \u0020 Cream']],
			],
			[
				'-A B C-',
				'abc',
				['delimiter' => ''],
			],
			[
				'-A B C-',
				'',
				['validChars' => ''],
			],
			[
				'contextöcontextöcontext',
				'CONTEXTöCONTEXTöCONTEXT',
				[
					'validChars' => 'A-Zö',
					'preTransforms' => ['ö > OOOO'],
				],
			],
		];
	}

	public function testGenerateThrowsExceptionForNonUtf8Text(): void
	{
		$generator = new SlugGenerator;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('(utf-?8)i');

		$generator->generate("\x80");
	}

	public function testGenerateThrowsExceptionForInvalidRule(): void
	{
		$generator = new SlugGenerator;

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMatches('("invalid rule".*"de_AT")');

		$generator->generate('foö', [
			'transforms' => ['invalid rule'],
			'locale' => 'de_AT',
		]);
	}

	/**
	 * @param array<string> $parameters
	 * @dataProvider getPrivateApplyTransformRule
	 */
	public function testPrivateApplyTransformRule(array $parameters, string $expected, bool $skip = false): void
	{
		if ($skip) {
			$this->markTestSkipped();
		}

		$generator = new SlugGenerator;
		$reflection = new \ReflectionClass(\get_class($generator));
		$method = $reflection->getMethod('applyTransformRule');
		$method->setAccessible(true);

		$this->assertSame($expected, $method->invokeArgs($generator, $parameters));
	}

	/**
	 * @return array<array>
	 */
	public function getPrivateApplyTransformRule(): array
	{
		return [
			[
				['abc', 'Upper', '', '/b+/'],
				'aBc',
			],
			[
				['öbc', 'Upper', '', '/b+/'],
				'öBc',
			],
			[
				['💩bc', 'Upper', '', '/b+/'],
				'💩Bc',
			],
			[
				['iı', 'Upper', 'tr', '/.+/'],
				'İI',
				version_compare(INTL_ICU_VERSION, '51.2', '<'),
			],
			[
				['iı', 'Upper', '', '/.+/'],
				'II',
			],
			[
				['İI', 'Lower', 'tr_Latn_AT', '/.+/'],
				'iı',
				version_compare(INTL_ICU_VERSION, '51.2', '<'),
			],
			[
				['İI', 'Lower', '', '/.+/'],
				'i̇i',
			],
			[
				['öß', 'ASCII', '', '/.+/'],
				'oss',
			],
			[
				['öß', 'ASCII', 'de', '/.+/'],
				'oess',
			],
		];
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
