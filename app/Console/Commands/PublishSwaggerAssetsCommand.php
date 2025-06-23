<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class PublishSwaggerAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:publish-assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Swagger UI assets to the public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileSystem = new Filesystem();

        $sourceDir = base_path('vendor/swagger-api/swagger-ui/dist');
        $destinationDir = public_path('swagger-ui');

        // Create the destination directory if it doesn't exist
        if (!$fileSystem->exists($destinationDir)) {
            $fileSystem->makeDirectory($destinationDir, 0755, true);
            $this->info("Created directory: {$destinationDir}");
        }

        // List of files to copy
        $filesToCopy = [
            'swagger-ui.css',
            'swagger-ui-bundle.js',
            'swagger-ui-standalone-preset.js',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'oauth2-redirect.html'
        ];

        // Copy each file
        foreach ($filesToCopy as $file) {
            $sourcePath = $sourceDir . '/' . $file;
            $destinationPath = $destinationDir . '/' . $file;

            if ($fileSystem->exists($sourcePath)) {
                $fileSystem->copy($sourcePath, $destinationPath);
                $this->info("Copied: {$file}");
            } else {
                $this->warn("File not found: {$sourcePath}");
            }
        }

        $this->info('Swagger UI assets have been published to the public directory');
        return 0;
    }
}
