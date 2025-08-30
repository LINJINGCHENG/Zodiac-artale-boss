# Zodiac-artale-boss
The form for Zoidac members to fill out and form teams to fight the Boss.

A PHP and MySQL-based team scheduling survey system for Latus group coordination, providing user registration, login, time scheduling, and results viewing functionality.

## System Overview

This system employs PHP session management for user authentication, utilizes PDO for secure database connections, and implements comprehensive user management and time scheduling features [1].

## Main Features

### User Management

- **User Registration** (`register.php`): New users can create accounts
- **User Login** (`login.php`): Existing user authentication  
- **User Logout** (`logout.php`): Secure session cleanup
- **Admin Privileges**: Support for administrator and regular user permission differentiation

### Time Management

- **Time Survey** (`investigate.php`): Users can set their available time slots
- **Data Processing** (`process.php`): Handle form submissions and data validation
- **Results Viewing** (`results.php`): Display all users' time scheduling results

## Technical Architecture

```php
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

The system uses PDO (PHP Data Objects) for secure MySQL database connections.

### Security Features

- **Session Management**: Uses PHP sessions for user state management
- **Password Encryption**: Implements secure password hashing mechanisms
- **Input Validation**: Validates and sanitizes all user inputs
- **SQL Injection Protection**: Uses PDO prepared statements to prevent SQL injection attacks 

### Access Control

- All protected pages check login status
- Administrator users have additional system management privileges
- Unauthenticated users are redirected to the login page

## File Structure

| File Name | Function Description |
|-----------|---------------------|
| `login.php` | User login page and authentication |
| `register.php` | User registration page and account creation |
| `logout.php` | User logout and session cleanup |
| `investigate.php` | Time availability survey form |
| `process.php` | Form data processing and validation |
| `results.php` | Results display and data viewing |

## Installation and Deployment

### System Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache/Nginx)

### Deployment Steps

1. Upload all PHP files to web root directory
2. Configure database connection parameters
3. Ensure PHP session functionality is enabled
4. Set appropriate file permissions

## Security Considerations

The system implements multi-layered security protection:

- Session-based authentication
- PDO prepared statements to prevent SQL injection
- Input data validation and sanitization
- Secure password hash storage

## Usage Workflow

1. **New Users**: Visit `register.php` to create an account
2. **Login**: Use `login.php` for authentication
3. **Set Time**: Fill out available time through `investigate.php`
4. **View Results**: Check all users' time schedules in `results.php`
5. **Logout**: Use `logout.php` to safely exit the system

## Maintenance and Support

- Regularly check database connection status
- Monitor session management and user activity
- Keep PHP and MySQL versions updated
- Regularly backup user data and system configuration

---

> **Note**: This system contains sensitive database connection information. Please ensure proper protection of these credentials in production environments.

## Contributing

Please read the contribution guidelines before submitting pull requests.

## License

This project is licensed under the  Apache License - see the LICENSE file for details.







