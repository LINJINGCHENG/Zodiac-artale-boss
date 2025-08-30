# Zodiac-artale-boss
The form for Zoidac members to fill out and form teams to fight the Boss.

# Time Availability Management System

A PHP and MySQL-based team scheduling survey system for Latus group coordination, providing user registration, login, time scheduling, and results viewing functionality.

## System Overview

This system employs PHP session management for user authentication, utilizes PDO for secure database connections, and implements comprehensive user management and time scheduling features .

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

### Database Connection
The system uses PDO (PHP Data Objects) for secure MySQL database connections :

```php
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
