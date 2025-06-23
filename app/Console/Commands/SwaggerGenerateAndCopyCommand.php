<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SwaggerGenerateAndCopyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swagger:generate-and-copy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Swagger documentation and copy it to the public directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Generate the Swagger documentation
        $this->info('Generating Swagger documentation...');
        $this->call('l5-swagger:generate');

        // Copy the Swagger JSON file to the public directory
        $this->info('Copying Swagger JSON file to public directory...');
        $this->call('swagger:copy');

        $this->info('Swagger documentation has been generated and copied to the public directory');
        return 0;
    }
}
