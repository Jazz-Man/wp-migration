<?php

use function JazzMan\PhpCsFixerRules\phpCsFixerConfig;

require_once __DIR__.'/vendor/jazzman/php-cs-fixer-rules/src/rules-config.php';

$project_dir = __DIR__.'/wp';

return phpCsFixerConfig( $project_dir );
