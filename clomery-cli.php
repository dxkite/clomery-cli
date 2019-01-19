<?php

use clomery\command\PostScanCommand;
use clomery\command\PostArticleCommand;
use clomery\command\PostAnalysisCommand;
use clomery\command\PostGenerateCommand;
use Symfony\Component\Console\Application;

require_once __DIR__.'/vendor/autoload.php';

$app = new Application;

$app->add(new PostScanCommand);
$app->add(new PostGenerateCommand);
$app->add(new PostAnalysisCommand);
$app->add(new PostArticleCommand);
$app->run();