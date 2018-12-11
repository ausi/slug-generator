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

namespace Ausi\SlugGenerator\Tests;

use Ausi\SlugGenerator\SlugGeneratorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Martin Auswöger <martin@auswoeger.com>
 */
class SlugGeneratorInterfaceTest extends TestCase
{
	public function testInterfaceImplementation()
	{
		$this->assertSame('slug', (new class implements SlugGeneratorInterface {
			public function generate(string $param1, iterable $param2 = []): string
			{
				return $param1;
			}
		})->generate('slug'));
	}
}
