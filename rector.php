<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;


return static function (RectorConfig $config): void {
	$config->fileExtensions(['php']);
	$config->importNames();
	$config->removeUnusedImports();
	$config->importShortClasses(false);
	$config->disableParallel();
	$config->cacheDirectory(__DIR__.'/cache/rector');


	$config->paths([
        __DIR__ . '/wp',
    ]);


	$config->skip([
		__DIR__.'/vendor',
		__DIR__.'/cache',
		__DIR__.'/wp/wp-admin/css',
		__DIR__.'/wp/wp-admin/images',
		__DIR__.'/wp/wp-admin/js',

		__DIR__.'/wp/wp-includes/certificates',
		__DIR__.'/wp/wp-includes/css',
		__DIR__.'/wp/wp-includes/fonts',
		__DIR__.'/wp/wp-includes/images',
		__DIR__.'/wp/wp-includes/js',
	]);

    // register a single rule
	$config->rule(InlineConstructorDefaultToPropertyRector::class);

    // define sets of rules
    //    $rectorConfig->sets([
    //        LevelSetList::UP_TO_PHP_82
    //    ]);
};
