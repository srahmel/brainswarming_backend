# Swagger Commands

This directory contains custom Artisan commands for working with Swagger/OpenAPI documentation.

## Available Commands

### `swagger:publish-assets`

Publishes the Swagger UI assets (CSS, JavaScript, and images) from the vendor package to the public directory.

```bash
php artisan swagger:publish-assets
```

This command should be run after installing or updating the Swagger UI package to ensure that the assets are available in the public directory.

### `swagger:copy`

Copies the Swagger JSON file from the storage directory to the public directory.

```bash
php artisan swagger:copy
```

### `swagger:generate-and-copy`

Generates the Swagger documentation and then copies it to the public directory.

```bash
php artisan swagger:generate-and-copy
```

## Troubleshooting

If you encounter 404 errors for Swagger UI assets (CSS, JavaScript, etc.) when viewing the documentation, run the `swagger:publish-assets` command to publish the assets to the public directory.
