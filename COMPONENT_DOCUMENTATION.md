# Component Documentation

## Overview

This document provides comprehensive documentation for all components, classes, and interfaces in the project.

## Current Project State

**Status**: Minimal implementation with empty `index.php`. This documentation provides templates and structures for future development.

## Core Components

### 1. HTTP Client Component

#### HttpClient Class

```php
/**
 * HTTP Client for making external requests
 * 
 * @package App\Http
 * @version 1.0.0
 */
class HttpClient {
    
    private $defaultOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ];
    
    /**
     * Constructor
     * 
     * @param array $defaultOptions Default cURL options
     */
    public function __construct(array $defaultOptions = []) {
        $this->defaultOptions = array_merge($this->defaultOptions, $defaultOptions);
    }
    
    /**
     * Performs a GET request
     * 
     * @param string $url The URL to request
     * @param array $headers Optional headers
     * @param array $options Optional cURL options
     * @return HttpResponse Response object
     * 
     * @example
     * $client = new HttpClient();
     * $response = $client->get('https://api.example.com/users');
     * if ($response->isSuccess()) {
     *     $users = $response->json();
     * }
     */
    public function get(string $url, array $headers = [], array $options = []): HttpResponse {
        return $this->request('GET', $url, null, $headers, $options);
    }
    
    /**
     * Performs a POST request
     * 
     * @param string $url The URL to request
     * @param mixed $data Data to send
     * @param array $headers Optional headers
     * @param array $options Optional cURL options
     * @return HttpResponse Response object
     */
    public function post(string $url, $data = null, array $headers = [], array $options = []): HttpResponse {
        return $this->request('POST', $url, $data, $headers, $options);
    }
    
    /**
     * Performs an HTTP request
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param mixed $data Request data
     * @param array $headers Request headers
     * @param array $options cURL options
     * @return HttpResponse Response object
     */
    private function request(string $method, string $url, $data = null, array $headers = [], array $options = []): HttpResponse {
        // Implementation here
    }
}
```

#### HttpResponse Class

```php
/**
 * HTTP Response wrapper
 * 
 * @package App\Http
 */
class HttpResponse {
    
    private $body;
    private $statusCode;
    private $headers;
    
    /**
     * Constructor
     * 
     * @param string $body Response body
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     */
    public function __construct(string $body, int $statusCode, array $headers = []) {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    /**
     * Get response body
     * 
     * @return string
     */
    public function getBody(): string {
        return $this->body;
    }
    
    /**
     * Get status code
     * 
     * @return int
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }
    
    /**
     * Check if response is successful
     * 
     * @return bool
     */
    public function isSuccess(): bool {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
    
    /**
     * Parse response as JSON
     * 
     * @return array|null
     */
    public function json(): ?array {
        return json_decode($this->body, true);
    }
}
```

### 2. Database Component

#### DatabaseManager Class

```php
/**
 * Database Manager for handling database operations
 * 
 * @package App\Database
 */
class DatabaseManager {
    
    private $connection;
    private $config;
    
    /**
     * Constructor
     * 
     * @param array $config Database configuration
     */
    public function __construct(array $config) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Establish database connection
     * 
     * @throws DatabaseException If connection fails
     */
    private function connect(): void {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch (PDOException $e) {
            throw new DatabaseException("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement
     * 
     * @example
     * $db = new DatabaseManager($config);
     * $stmt = $db->query("SELECT * FROM users WHERE id = ?", [123]);
     * $user = $stmt->fetch();
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Find record by ID
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @return array|null
     */
    public function find(string $table, int $id): ?array {
        $stmt = $this->query("SELECT * FROM {$table} WHERE id = ?", [$id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Insert new record
     * 
     * @param string $table Table name
     * @param array $data Record data
     * @return int Insert ID
     */
    public function insert(string $table, array $data): int {
        $fields = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Update existing record
     * 
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update(string $table, int $id, array $data): bool {
        $fields = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$fields} WHERE id = ?";
        
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }
}
```

### 3. Authentication Component

#### AuthManager Class

```php
/**
 * Authentication Manager
 * 
 * @package App\Auth
 */
class AuthManager {
    
    private $userProvider;
    private $sessionManager;
    
    /**
     * Constructor
     * 
     * @param UserProviderInterface $userProvider User data provider
     * @param SessionManagerInterface $sessionManager Session handler
     */
    public function __construct(UserProviderInterface $userProvider, SessionManagerInterface $sessionManager) {
        $this->userProvider = $userProvider;
        $this->sessionManager = $sessionManager;
    }
    
    /**
     * Authenticate user with credentials
     * 
     * @param string $username Username or email
     * @param string $password Password
     * @return AuthResult Authentication result
     * 
     * @example
     * $auth = new AuthManager($userProvider, $sessionManager);
     * $result = $auth->attempt('user@example.com', 'password');
     * if ($result->isSuccess()) {
     *     $user = $result->getUser();
     * }
     */
    public function attempt(string $username, string $password): AuthResult {
        $user = $this->userProvider->findByCredentials($username);
        
        if (!$user || !$this->verifyPassword($password, $user->getPassword())) {
            return new AuthResult(false, null, 'Invalid credentials');
        }
        
        $this->sessionManager->login($user);
        return new AuthResult(true, $user);
    }
    
    /**
     * Get currently authenticated user
     * 
     * @return User|null
     */
    public function user(): ?User {
        return $this->sessionManager->getUser();
    }
    
    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public function check(): bool {
        return $this->user() !== null;
    }
    
    /**
     * Logout current user
     * 
     * @return void
     */
    public function logout(): void {
        $this->sessionManager->logout();
    }
    
    /**
     * Verify password hash
     * 
     * @param string $password Plain password
     * @param string $hash Hashed password
     * @return bool
     */
    private function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
```

#### User Model

```php
/**
 * User Model
 * 
 * @package App\Models
 */
class User {
    
    private $id;
    private $username;
    private $email;
    private $password;
    private $roles;
    private $createdAt;
    
    /**
     * Constructor
     * 
     * @param array $data User data
     */
    public function __construct(array $data) {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->roles = $data['roles'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
    }
    
    /**
     * Get user ID
     * 
     * @return int|null
     */
    public function getId(): ?int {
        return $this->id;
    }
    
    /**
     * Get username
     * 
     * @return string
     */
    public function getUsername(): string {
        return $this->username;
    }
    
    /**
     * Get email
     * 
     * @return string
     */
    public function getEmail(): string {
        return $this->email;
    }
    
    /**
     * Get password hash
     * 
     * @return string
     */
    public function getPassword(): string {
        return $this->password;
    }
    
    /**
     * Check if user has role
     * 
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool {
        return in_array($role, $this->roles);
    }
    
    /**
     * Convert to array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'roles' => $this->roles,
            'created_at' => $this->createdAt
        ];
    }
}
```

### 4. Validation Component

#### Validator Class

```php
/**
 * Input Validator
 * 
 * @package App\Validation
 */
class Validator {
    
    private $rules = [];
    private $messages = [];
    private $errors = [];
    
    /**
     * Available validation rules
     */
    private const RULES = [
        'required' => 'validateRequired',
        'email' => 'validateEmail',
        'min' => 'validateMin',
        'max' => 'validateMax',
        'numeric' => 'validateNumeric',
        'string' => 'validateString',
        'array' => 'validateArray',
        'url' => 'validateUrl',
    ];
    
    /**
     * Constructor
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     */
    public function __construct(array $data, array $rules, array $messages = []) {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }
    
    /**
     * Validate the data
     * 
     * @return bool True if validation passes
     * 
     * @example
     * $validator = new Validator($_POST, [
     *     'email' => 'required|email',
     *     'password' => 'required|min:8',
     *     'age' => 'numeric|min:18'
     * ]);
     * 
     * if ($validator->validate()) {
     *     // Process valid data
     * } else {
     *     $errors = $validator->getErrors();
     * }
     */
    public function validate(): bool {
        $this->errors = [];
        
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->validateField($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Validate a single field
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     */
    private function validateField(string $field, $value, string $rule): void {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;
        
        if (!isset(self::RULES[$ruleName])) {
            return;
        }
        
        $method = self::RULES[$ruleName];
        if (!$this->$method($value, $parameter)) {
            $this->addError($field, $ruleName, $parameter);
        }
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param string|null $parameter Rule parameter
     */
    private function addError(string $field, string $rule, ?string $parameter): void {
        $message = $this->messages["{$field}.{$rule}"] ?? $this->getDefaultMessage($field, $rule, $parameter);
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get default error message
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param string|null $parameter Rule parameter
     * @return string
     */
    private function getDefaultMessage(string $field, string $rule, ?string $parameter): string {
        $messages = [
            'required' => "The {$field} field is required.",
            'email' => "The {$field} must be a valid email address.",
            'min' => "The {$field} must be at least {$parameter}.",
            'max' => "The {$field} must not exceed {$parameter}.",
            'numeric' => "The {$field} must be a number.",
            'string' => "The {$field} must be a string.",
            'array' => "The {$field} must be an array.",
            'url' => "The {$field} must be a valid URL.",
        ];
        
        return $messages[$rule] ?? "The {$field} is invalid.";
    }
    
    // Validation methods
    private function validateRequired($value, $parameter): bool {
        return !empty($value);
    }
    
    private function validateEmail($value, $parameter): bool {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validateMin($value, $parameter): bool {
        if (is_string($value)) {
            return strlen($value) >= (int) $parameter;
        }
        return $value >= (int) $parameter;
    }
    
    private function validateMax($value, $parameter): bool {
        if (is_string($value)) {
            return strlen($value) <= (int) $parameter;
        }
        return $value <= (int) $parameter;
    }
    
    private function validateNumeric($value, $parameter): bool {
        return is_numeric($value);
    }
    
    private function validateString($value, $parameter): bool {
        return is_string($value);
    }
    
    private function validateArray($value, $parameter): bool {
        return is_array($value);
    }
    
    private function validateUrl($value, $parameter): bool {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
```

## Interfaces

### UserProviderInterface

```php
/**
 * User Provider Interface
 * 
 * @package App\Contracts
 */
interface UserProviderInterface {
    
    /**
     * Find user by credentials
     * 
     * @param string $identifier Username or email
     * @return User|null
     */
    public function findByCredentials(string $identifier): ?User;
    
    /**
     * Find user by ID
     * 
     * @param int $id User ID
     * @return User|null
     */
    public function findById(int $id): ?User;
    
    /**
     * Create new user
     * 
     * @param array $data User data
     * @return User
     */
    public function create(array $data): User;
    
    /**
     * Update user
     * 
     * @param int $id User ID
     * @param array $data Updated data
     * @return bool
     */
    public function update(int $id, array $data): bool;
}
```

### SessionManagerInterface

```php
/**
 * Session Manager Interface
 * 
 * @package App\Contracts
 */
interface SessionManagerInterface {
    
    /**
     * Login user
     * 
     * @param User $user User to login
     * @return void
     */
    public function login(User $user): void;
    
    /**
     * Logout current user
     * 
     * @return void
     */
    public function logout(): void;
    
    /**
     * Get current user
     * 
     * @return User|null
     */
    public function getUser(): ?User;
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn(): bool;
}
```

## Component Usage Examples

### Basic Application Structure

```php
<?php
// app/bootstrap.php

// Configuration
$config = [
    'database' => [
        'host' => $_ENV['DB_HOST'],
        'database' => $_ENV['DB_NAME'],
        'username' => $_ENV['DB_USER'],
        'password' => $_ENV['DB_PASS'],
    ]
];

// Initialize components
$database = new DatabaseManager($config['database']);
$userProvider = new DatabaseUserProvider($database);
$sessionManager = new SessionManager();
$auth = new AuthManager($userProvider, $sessionManager);
$httpClient = new HttpClient();

// Example API endpoint
try {
    // Authentication check
    if (!$auth->check()) {
        jsonResponse(null, 401, 'Authentication required');
    }
    
    // Validate input
    $validator = new Validator($_POST, [
        'name' => 'required|string|min:2',
        'email' => 'required|email',
    ]);
    
    if (!$validator->validate()) {
        jsonResponse(null, 400, 'Validation failed', $validator->getErrors());
    }
    
    // Process request
    $userId = $database->insert('users', [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    jsonResponse(['id' => $userId], 201, 'User created successfully');
    
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(null, 500, 'Internal server error');
}
```

### Component Integration Example

```php
<?php
// api/users.php

class UserController {
    
    private $auth;
    private $database;
    private $validator;
    
    public function __construct(AuthManager $auth, DatabaseManager $database) {
        $this->auth = $auth;
        $this->database = $database;
    }
    
    /**
     * Get user list
     * 
     * @return void
     */
    public function index(): void {
        if (!$this->auth->check()) {
            jsonResponse(null, 401, 'Authentication required');
        }
        
        $users = $this->database->query("SELECT id, username, email, created_at FROM users")->fetchAll();
        jsonResponse($users, 200, 'Users retrieved successfully');
    }
    
    /**
     * Create new user
     * 
     * @return void
     */
    public function store(): void {
        $validator = new Validator($_POST, [
            'username' => 'required|string|min:3|max:50',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        
        if (!$validator->validate()) {
            jsonResponse(null, 400, 'Validation failed', $validator->getErrors());
        }
        
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $userId = $this->database->insert('users', [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'password' => $hashedPassword,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        jsonResponse(['id' => $userId], 201, 'User created successfully');
    }
}
```

## Component Testing

### Unit Test Examples

```php
<?php
// tests/UserTest.php

class UserTest extends PHPUnit\Framework\TestCase {
    
    public function testUserCreation(): void {
        $userData = [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'roles' => ['user']
        ];
        
        $user = new User($userData);
        
        $this->assertEquals(1, $user->getId());
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
    }
}

// tests/ValidatorTest.php

class ValidatorTest extends PHPUnit\Framework\TestCase {
    
    public function testValidationPasses(): void {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'age' => 25
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'age' => 'numeric|min:18'
        ];
        
        $validator = new Validator($data, $rules);
        $this->assertTrue($validator->validate());
        $this->assertEmpty($validator->getErrors());
    }
    
    public function testValidationFails(): void {
        $data = [
            'email' => 'invalid-email',
            'password' => '123',
            'age' => 15
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'age' => 'numeric|min:18'
        ];
        
        $validator = new Validator($data, $rules);
        $this->assertFalse($validator->validate());
        $this->assertNotEmpty($validator->getErrors());
    }
}
```

---

**Last Updated**: January 2025  
**Version**: 2.0  
**Status**: Template for future development