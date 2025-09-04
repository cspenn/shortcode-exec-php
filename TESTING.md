# WordPress Quality Assurance Testing Setup

This document explains the comprehensive testing setup for the Shortcode Exec PHP WordPress plugin.

## Prerequisites

- PHP 7.4 or higher
- Composer for dependency management
- WordPress testing environment (optional but recommended)

## Installation

Install all testing dependencies using Composer:

```bash
composer install --dev
```

## Available Testing Tools

### 1. PHP CodeSniffer (PHPCS) with WordPress Coding Standards

Checks code against WordPress coding standards.

**Configuration**: `phpcs.xml`

**Usage**:
```bash
# Check all files
composer run phpcs

# Check specific files
./vendor/bin/phpcs shortcode-exec-php.php

# Auto-fix fixable issues
composer run phpcs-fix
./vendor/bin/phpcbf shortcode-exec-php.php
```

**Key features**:
- WordPress Coding Standards compliance
- PHP 7.4+ compatibility checking
- Excludes third-party libraries (EditArea, SimpleModal, TinyMCE)
- Custom rules for WordPress plugin development

### 2. PHP CS Fixer

Automatically fixes code style issues with WordPress-compatible rules.

**Configuration**: `.php-cs-fixer.php`

**Usage**:
```bash
# Dry run (show what would be fixed)
composer run php-cs-fixer

# Actually fix the files
composer run php-cs-fixer-fix
```

**Key features**:
- WordPress-compatible formatting
- Uses array() instead of [] (WordPress standard)
- Proper concatenation spacing
- Yoda conditions support

### 3. PHPUnit Testing Framework

Unit and integration testing for plugin functionality.

**Configuration**: `phpunit.xml`

**Usage**:
```bash
# Run all tests
composer run test

# Run specific test suite
./vendor/bin/phpunit --testsuite=unit
./vendor/bin/phpunit --testsuite=integration

# Run with coverage report
./vendor/bin/phpunit --coverage-html=coverage-html
```

**Test structure**:
- `tests/Unit/` - Unit tests for individual classes/functions
- `tests/Integration/` - Integration tests with WordPress
- `tests/bootstrap.php` - Test environment setup

### 4. PHPStan Static Analysis

Static code analysis to find type errors and logical issues.

**Configuration**: `phpstan.neon`

**Usage**:
```bash
# Analyze code
composer run phpstan

# Analyze with specific level (0-9)
./vendor/bin/phpstan analyse --level=6
```

**Key features**:
- WordPress-specific extensions
- Level 5 analysis (good balance for WordPress)
- Ignores common WordPress global variables
- Custom WordPress function stubs

### 5. Security Checker

Scans for known security vulnerabilities in dependencies and code patterns.

**Configuration**: `security-checker.yaml`

**Usage**:
```bash
# Check for vulnerabilities
composer run security-check

# Check with specific format
./vendor/bin/security-checker security:check composer.lock --format=json
```

**Security checks**:
- Known vulnerabilities in Composer dependencies
- WordPress-specific security patterns
- SQL injection detection
- XSS prevention validation
- File inclusion vulnerability detection

## Running All Tests

Run the complete quality assurance suite:

```bash
composer run qa
```

This will execute:
1. PHPCS code standards check
2. PHPStan static analysis
3. PHPUnit tests
4. Security vulnerability scan

## Continuous Integration

The testing tools are configured to work with CI/CD pipelines. Example GitHub Actions workflow:

```yaml
name: QA
on: [push, pull_request]
jobs:
  qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - run: composer install --dev --no-progress
      - run: composer run qa
```

## Configuration Customization

### PHPCS Customization

Edit `phpcs.xml` to:
- Add/remove specific rules
- Adjust error/warning levels
- Exclude additional files/directories

### PHPStan Level Adjustment

Edit `phpstan.neon` to change analysis level:
```neon
parameters:
    level: 6  # Increase for stricter analysis
```

### Test Environment Setup

For full WordPress integration testing:
1. Install WordPress test suite
2. Configure database connection
3. Update `tests/bootstrap.php`

## Best Practices

1. **Run tests before commits**: Use git hooks or CI/CD
2. **Fix PHPCS issues first**: They're usually the easiest to resolve
3. **Address PHPStan errors**: They often reveal real bugs
4. **Keep security checker updated**: Run regularly for new vulnerabilities
5. **Write tests for new features**: Maintain good test coverage

## Troubleshooting

### Common Issues

1. **Memory limit errors**: Increase PHP memory limit
2. **WordPress functions not found**: Install WordPress test suite
3. **Timeout issues**: Configure longer timeout in phpunit.xml
4. **Permission errors**: Check file permissions in tests directory

### Getting Help

- WordPress Coding Standards: https://github.com/WordPress/WordPress-Coding-Standards
- PHPStan WordPress Extension: https://github.com/szepeviktor/phpstan-wordpress
- PHPUnit Documentation: https://phpunit.de/documentation.html