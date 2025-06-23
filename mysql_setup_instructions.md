# MySQL Setup Instructions for Staging Server

## Diagnosing the Issue

The error "could not find driver" when running tests on the staging server indicates that the PHP MySQL extension is not installed or not properly configured. Follow these steps to diagnose and fix the issue:

## Step 1: Run the Diagnostic Script

Upload the `check_mysql.php` script to your staging server and run it:

```bash
php check_mysql.php
```

This will tell you if:
- The PDO MySQL extension is loaded
- The connection to the MySQL database works
- The migrations table exists
- What PHP extensions are currently loaded

## Step 2: Install MySQL Extension (if missing)

If the diagnostic script shows that the PDO MySQL extension is not loaded, you need to install it:

### For Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php-mysql
# Restart your web server
sudo service apache2 restart  # For Apache
# OR
sudo service nginx restart    # For Nginx
```

### For CentOS/RHEL:
```bash
sudo yum install php-mysql
# Restart your web server
sudo systemctl restart httpd  # For Apache
# OR
sudo systemctl restart nginx  # For Nginx
```

### For Windows:
1. Open your php.ini file (usually in C:\php or in your web server's configuration)
2. Uncomment the line `;extension=pdo_mysql` by removing the semicolon
3. Restart your web server

## Step 3: Verify Database Configuration

Make sure your `.env` file on the staging server has the correct MySQL configuration:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=srahmel_d3_brainswarming
DB_USERNAME=root
DB_PASSWORD=your_password
```

## Step 4: Run Migrations (if needed)

If the migrations table doesn't exist, run:

```bash
php artisan migrate
```

## Step 5: Run Tests with MySQL

To run tests with MySQL instead of SQLite, modify your `phpunit.xml` file:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="mysql"/>
    <env name="DB_DATABASE" value="srahmel_d3_brainswarming_testing"/>
    <!-- Other settings -->
</php>
```

Create a testing database:

```bash
mysql -u root -p
CREATE DATABASE srahmel_d3_brainswarming_testing;
exit;
```

Then run the tests:

```bash
php artisan test
```

## Troubleshooting

If you still encounter issues:

1. Check PHP error logs for more detailed information
2. Verify MySQL user permissions
3. Make sure the MySQL server is running
4. Try connecting to MySQL using a different client to verify credentials

## Additional Resources

- [Laravel Database Documentation](https://laravel.com/docs/10.x/database)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
