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

error_reporting(E_ALL);

$include = function ($file) {
	return file_exists($file) ? include $file : false;
};

if (
	false === ($loader = $include(__DIR__.'/../vendor/autoload.php'))
	&& false === ($loader = $include(__DIR__.'/../../../autoload.php'))
) {
	echo 'You must set up the project dependencies, run the following command:'.PHP_EOL
		.'composer install'.PHP_EOL;

	exit(1);
}

return $loader;
