<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SwaggerCopyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:copy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy Swagger JSON file from storage to public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileSystem = new Filesystem();

        $sourcePath = storage_path('api-docs/swagger.json');
        $destinationPath = public_path('api-docs/swagger.json');

        if (!$fileSystem->exists($sourcePath)) {
            $this->error("Source file does not exist: {$sourcePath}");
            return 1;
        }

        // Create the destination directory if it doesn't exist
        $destinationDir = dirname($destinationPath);
        if (!$fileSystem->exists($destinationDir)) {
            $fileSystem->makeDirectory($destinationDir, 0755, true);
        }

        // Copy the file
        $fileSystem->copy($sourcePath, $destinationPath);

        $this->info("Successfully copied Swagger JSON file to public directory");
        return 0;
    }
}
