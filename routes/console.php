<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\SwaggerCopyCommand;
use App\Console\Commands\SwaggerGenerateAndCopyCommand;
use App\Console\Commands\PublishSwaggerAssetsCommand;

// Register the SwaggerCopyCommand
$app = app();
$app->singleton('command.swagger.copy', function () {
    return new SwaggerCopyCommand();
});
$app->make('command.swagger.copy');

// Register the SwaggerGenerateAndCopyCommand
$app->singleton('command.swagger.generate-and-copy', function () {
    return new SwaggerGenerateAndCopyCommand();
});
$app->make('command.swagger.generate-and-copy');

// Register the PublishSwaggerAssetsCommand
$app->singleton('command.swagger.publish-assets', function () {
    return new PublishSwaggerAssetsCommand();
});
$app->make('command.swagger.publish-assets');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
