<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Operator\NewWithBracesFixer;
use SlevomatCodingStandard\Sniffs\Variables\UselessVariableSniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
	$containerConfigurator->import(__DIR__.'/vendor/contao/easy-coding-standard/config/set/contao.php');

	$parameters = $containerConfigurator->parameters();
	$parameters->set(Option::INDENTATION, 'tab');

	$parameters->set(Option::SKIP, [
		NewWithBracesFixer::class => null,
		UselessVariableSniff::class => null,
	]);

	$services = $containerConfigurator->services();
	$services
		->set(HeaderCommentFixer::class)
		->call('configure', [[
			'header' => "This file is part of the ausi/slug-generator package.\n\n(c) Martin Auswöger <martin@auswoeger.com>\n\nFor the full copyright and license information, please view the LICENSE\nfile that was distributed with this source code.",
		]]);

	$services
		->set(YodaStyleFixer::class)
		->call('configure', [[
			'equal' => false,
			'identical' => false,
			'less_and_greater' => false,
		]]);
};
