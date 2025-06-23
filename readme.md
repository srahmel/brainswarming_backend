# Laravel 12 API Backend – Brainswarming Platform

## Minimal Description
Brainswarming is a SaaS platform for teams to anonymously or openly submit, evaluate, and prioritize improvement ideas. All API endpoints require authentication via Laravel Sanctum (Bearer token) except for `/register`, `/login`, `/csrf-token`, and invite acceptance. All team/user/entry data is isolated per team and user policies.

---

## Database Requirements

- Use InnoDB, strict mode, utf8mb4.
- Primary keys: bigIncrements (or UUIDs if possible).
- Foreign keys on all relationships.
- **users**: id, name, email (unique), password (bcrypt), nickname (nullable), anonymous (boolean, default false), timestamps
- **teams**: id, name, team_code (unique), invite_token (unique), invite_expires_at, founder_user_id (FK to users), settings (JSON), deleted_at, timestamps
- **team_user**: user_id, team_id, is_admin (boolean), timestamps; unique(user_id, team_id); FKs for both columns
- **entries**: id, team_id, user_id, problem, solution, area, time_saved_per_year (nullable, int), gross_profit_per_year (nullable, int), effort (enum), monetary_explanation (text), link (nullable, url), anonymous (boolean), manual_override_prio (int, default 0), final_prio (int), created_at, updated_at, deleted_at; FKs for team_id, user_id

- Use softDeletes where applicable.

---

## Authentication

- Use Laravel Sanctum for API authentication.
- `/register`, `/login`, `/csrf-token` (GET) and `/teams/invite/accept` are public endpoints.
- All other routes require bearer token auth and `auth:sanctum` middleware.
- Issue and revoke tokens via Sanctum best practices.

---

## Routes & Security

- All `/teams/*`, `/entries/*`, `/settings/*`, and export routes require `auth:sanctum` and are only accessible to authenticated users with the proper team association (policy-based).
- Use Laravel policies for all models to enforce team and admin/user ownership.
- Provide a GET `/csrf-token` endpoint returning a CSRF cookie/token, compatible with SPA and v0 tools.
- Never expose data across teams or outside policies.

---

## API Endpoints with Examples

### Public Endpoints

#### `POST /register`
Register a new user.

**Input:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "password_confirmation": "securepassword",
  "nickname": "Johnny"
}
```

**Output:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "nickname": "Johnny",
    "anonymous": false,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  },
  "token": "1|laravel_sanctum_token_example"
}
```

#### `POST /login`
Authenticate a user and get a token.

**Input:**
```json
{
  "email": "john@example.com",
  "password": "securepassword"
}
```

**Output:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "nickname": "Johnny",
    "anonymous": false,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  },
  "token": "1|laravel_sanctum_token_example"
}
```

#### `GET /csrf-token`
Get a CSRF token for SPA authentication.

**Input:** None

**Output:**
```json
{
  "csrf_token": "example_csrf_token_string"
}
```

#### `POST /teams/invite/accept`
Accept an invitation to join a team.

**Input:**
```json
{
  "invite_token": "unique_invite_token_string"
}
```

**Output:**
```json
{
  "message": "Invitation accepted successfully",
  "team": {
    "id": 1,
    "name": "Development Team",
    "team_code": "DEV123",
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

---

### Authenticated Endpoints (Bearer token, `auth:sanctum`)

#### `GET /teams`
Get a list of teams the authenticated user belongs to.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "teams": [
    {
      "id": 1,
      "name": "Development Team",
      "team_code": "DEV123",
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z",
      "is_admin": true
    },
    {
      "id": 2,
      "name": "Marketing Team",
      "team_code": "MKT456",
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z",
      "is_admin": false
    }
  ]
}
```

#### `POST /teams`
Create a new team.

**Input:**
```json
{
  "name": "New Project Team",
  "team_code": "NPT789"
}
```

**Output:**
```json
{
  "team": {
    "id": 3,
    "name": "New Project Team",
    "team_code": "NPT789",
    "founder_user_id": 1,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `POST /teams/join`
Join a team using a team code.

**Input:**
```json
{
  "team_code": "DEV123"
}
```

**Output:**
```json
{
  "message": "Successfully joined the team",
  "team": {
    "id": 1,
    "name": "Development Team",
    "team_code": "DEV123",
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `GET /teams/join/{token}`
Join a team using an invite link token.

**Input:** None (Authentication token in header, invite token in URL)

**Output:**
```json
{
  "message": "Successfully joined the team",
  "team": {
    "id": 1,
    "name": "Development Team",
    "team_code": "DEV123",
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `DELETE /teams/{teamId}/leave`
Leave a team.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "message": "Successfully left the team"
}
```

#### `DELETE /teams/{team}`
Delete a team (only available to team admin).

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "message": "Team deleted successfully"
}
```

#### `PATCH /teams/{team}/settings`
Update team settings.

**Input:**
```json
{
  "settings": {
    "allow_anonymous_entries": true,
    "require_approval": false
  }
}
```

**Output:**
```json
{
  "message": "Team settings updated successfully",
  "team": {
    "id": 1,
    "name": "Development Team",
    "team_code": "DEV123",
    "settings": {
      "allow_anonymous_entries": true,
      "require_approval": false
    },
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `POST /teams/{teamId}/invite/generate`
Generate a new invite link for a team.

**Input:**
```json
{
  "expires_in_days": 7
}
```

**Output:**
```json
{
  "message": "Invite link generated successfully",
  "invite_token": "unique_invite_token_string",
  "invite_link": "https://example.com/api/teams/join/unique_invite_token_string",
  "expires_at": "2023-06-17T12:00:00.000000Z"
}
```

#### `GET /teams/{team}/entries`
Get all entries for a team.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "entries": [
    {
      "id": 1,
      "team_id": 1,
      "user_id": 1,
      "problem": "Slow deployment process",
      "solution": "Implement CI/CD pipeline",
      "area": "Development",
      "time_saved_per_year": 520,
      "gross_profit_per_year": 50000,
      "effort": "medium",
      "monetary_explanation": "Save 10 hours per week at $100/hour",
      "link": "https://example.com/cicd",
      "anonymous": false,
      "final_prio": 1,
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z"
    }
  ]
}
```

#### `POST /teams/{team}/entries`
Create a new entry for a team.

**Input:**
```json
{
  "problem": "Manual testing is time-consuming",
  "solution": "Implement automated testing",
  "area": "QA",
  "time_saved_per_year": 260,
  "gross_profit_per_year": 26000,
  "effort": "high",
  "monetary_explanation": "Save 5 hours per week at $100/hour",
  "link": "https://example.com/automated-testing",
  "anonymous": true
}
```

**Output:**
```json
{
  "entry": {
    "id": 2,
    "team_id": 1,
    "user_id": 1,
    "problem": "Manual testing is time-consuming",
    "solution": "Implement automated testing",
    "area": "QA",
    "time_saved_per_year": 260,
    "gross_profit_per_year": 26000,
    "effort": "high",
    "monetary_explanation": "Save 5 hours per week at $100/hour",
    "link": "https://example.com/automated-testing",
    "anonymous": true,
    "final_prio": 2,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `PATCH /teams/{team}/entries/{entry}`
Update an existing entry.

**Input:**
```json
{
  "solution": "Implement automated testing with Jest",
  "effort": "medium"
}
```

**Output:**
```json
{
  "entry": {
    "id": 2,
    "team_id": 1,
    "user_id": 1,
    "problem": "Manual testing is time-consuming",
    "solution": "Implement automated testing with Jest",
    "area": "QA",
    "time_saved_per_year": 260,
    "gross_profit_per_year": 26000,
    "effort": "medium",
    "monetary_explanation": "Save 5 hours per week at $100/hour",
    "link": "https://example.com/automated-testing",
    "anonymous": true,
    "final_prio": 2,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z"
  }
}
```

#### `DELETE /teams/{team}/entries/{entry}`
Soft delete an entry.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "message": "Entry deleted successfully"
}
```

#### `GET /teams/{team}/entries/deleted`
Get all soft-deleted entries for a team.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "entries": [
    {
      "id": 2,
      "team_id": 1,
      "user_id": 1,
      "problem": "Manual testing is time-consuming",
      "solution": "Implement automated testing with Jest",
      "area": "QA",
      "time_saved_per_year": 260,
      "gross_profit_per_year": 26000,
      "effort": "medium",
      "monetary_explanation": "Save 5 hours per week at $100/hour",
      "link": "https://example.com/automated-testing",
      "anonymous": true,
      "final_prio": 2,
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z",
      "deleted_at": "2023-06-11T12:00:00.000000Z"
    }
  ]
}
```

#### `POST /teams/{team}/entries/{entry}/restore`
Restore a soft-deleted entry.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "message": "Entry restored successfully",
  "entry": {
    "id": 2,
    "team_id": 1,
    "user_id": 1,
    "problem": "Manual testing is time-consuming",
    "solution": "Implement automated testing with Jest",
    "area": "QA",
    "time_saved_per_year": 260,
    "gross_profit_per_year": 26000,
    "effort": "medium",
    "monetary_explanation": "Save 5 hours per week at $100/hour",
    "link": "https://example.com/automated-testing",
    "anonymous": true,
    "final_prio": 2,
    "created_at": "2023-06-10T12:00:00.000000Z",
    "updated_at": "2023-06-10T12:00:00.000000Z",
    "deleted_at": null
  }
}
```

#### `GET /teams/{team}/entries/export`
Export all team entries to a downloadable format.

**Input:** None (Authentication token in header)

**Output:** A downloadable file (CSV, Excel, etc.) containing all team entries.

#### `POST /teams/{team}/admins/add`
Add a user as an admin to a team.

**Input:**
```json
{
  "user_id": 2
}
```

**Output:**
```json
{
  "message": "User added as admin successfully"
}
```

#### `POST /teams/{team}/admins/remove`
Remove admin privileges from a user.

**Input:**
```json
{
  "user_id": 2
}
```

**Output:**
```json
{
  "message": "Admin privileges removed successfully"
}
```

#### `GET /me/teams`
Get all teams for the authenticated user.

**Input:** None (Authentication token in header)

**Output:**
```json
{
  "teams": [
    {
      "id": 1,
      "name": "Development Team",
      "team_code": "DEV123",
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z",
      "is_admin": true
    },
    {
      "id": 2,
      "name": "Marketing Team",
      "team_code": "MKT456",
      "created_at": "2023-06-10T12:00:00.000000Z",
      "updated_at": "2023-06-10T12:00:00.000000Z",
      "is_admin": false
    }
  ]
}
```

---

## Security & Best Practice

- Hash all passwords with bcrypt.
- Validate all user inputs (including invite tokens, emails, etc).
- Strict foreign key enforcement and cascading softDeletes for user/team/entry relationships.
- API Resource responses for all data.
- Proper CORS and CSRF handling (support SPA usage).
- Never expose user emails or PII unless allowed by anonymous setting.

---

## API Documentation with Swagger/OpenAPI

The API is documented using the OpenAPI 3.0 specification. The swagger.json file is automatically generated from annotations in the controllers and is available at `/public/swagger.json`.

### Automatic Swagger Generation

This project uses the `darkaonline/l5-swagger` package to automatically generate Swagger documentation from annotations in the controllers. Here's how it works:

1. Controllers are annotated with Swagger annotations (e.g., `@OA\Info`, `@OA\Get`, `@OA\Post`, etc.)
2. The l5-swagger package scans these annotations and generates a swagger.json file
3. The swagger.json file is used by Swagger UI to display the API documentation

To regenerate the swagger.json file after making changes to the annotations, run:

```bash
php artisan l5-swagger:generate
```

You can also set the `L5_SWAGGER_GENERATE_ALWAYS` environment variable to `true` in your `.env` file to automatically regenerate the documentation on each request (useful in development):

```
L5_SWAGGER_GENERATE_ALWAYS=true
```

### Accessing Swagger UI

The Swagger UI is available at `/documentation`. This provides a user-friendly interface to explore and test the API endpoints.

### Adding Swagger Annotations

When adding new controllers or endpoints, make sure to add appropriate Swagger annotations to ensure they are included in the documentation. Here's an example of how to annotate a controller method:

```php
/**
 * @OA\Post(
 *     path="/example",
 *     summary="Example endpoint",
 *     tags={"Example"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Example Name")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Success")
 *         )
 *     )
 * )
 */
public function example(Request $request)
{
    // Method implementation
}
```

For more information on Swagger annotations, refer to the [swagger-php documentation](https://zircote.github.io/swagger-php/guide/annotations.html).

---

## Installation Instructions

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 5.7 or higher
- Node.js and npm (for frontend assets)

### Step 1: Clone the Repository
```bash
git clone <repository-url>
cd brainswarming
```

### Step 2: Install Dependencies
```bash
composer install
npm install
```

### Step 3: Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file to configure your database connection:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=brainswarming
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 4: Run Migrations and Seeders
```bash
php artisan migrate
php artisan db:seed
```

### Step 5: Generate Swagger Documentation
```bash
php artisan l5-swagger:generate
```

### Step 6: Start the Development Server
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api`.
The Swagger documentation will be available at `http://localhost:8000/documentation`.

---

## Testing

### Configuration
The project is configured to use MySQL for testing by default. This ensures that tests run in an environment that matches the production database.

To run tests, you need to:

1. Create a testing database:
```sql
CREATE DATABASE brainswarming_testing;
```

2. Configure the testing environment in `.env.testing`:
```
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=brainswarming_testing
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Running Tests
```bash
php artisan test
```

### Alternative: Using SQLite for Testing
If you prefer to use SQLite for testing, you can modify `phpunit.xml`:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <!-- Other settings -->
</php>
```

And update `.env.testing`:
```
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Make sure the SQLite driver is installed:
```bash
# For Ubuntu/Debian
sudo apt-get install php-sqlite3

# For Windows
# Uncomment ;extension=pdo_sqlite in php.ini
```

---

## Deliverables

- All models, migrations, policies, controllers, API resources, routes, FormRequests, and OpenAPI docs.
- Use .env for database and Sanctum config.
- Example factories/seeds for rapid dev.
- Clean, commented code using Laravel 12 conventions.

---

## CSRF Endpoint Example

```php
// routes/api.php
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
