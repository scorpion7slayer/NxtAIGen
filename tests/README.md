# NxtGenAI Test Suite

This directory contains comprehensive tests for the NxtGenAI platform.

## Overview

The test suite uses **PHPUnit 11** to ensure code quality and reliability for security-critical components.

## Structure

```
tests/
├── Unit/           # Unit tests for individual functions/classes
│   └── Api/        # API-related tests
│       └── ApiKeysHelperTest.php  # Encryption & API key management tests
└── Integration/    # Integration tests (future)
```

## Running Tests

### Run All Tests

```bash
./vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
./vendor/bin/phpunit tests/Unit

# Specific test file
./vendor/bin/phpunit tests/Unit/Api/ApiKeysHelperTest.php
```

### Run with Coverage (requires Xdebug or pcov)

```bash
./vendor/bin/phpunit --coverage-html coverage
```

### Run Specific Test Method

```bash
./vendor/bin/phpunit --filter testEncryptDecryptRoundtrip
```

## Current Test Coverage

### ApiKeysHelperTest.php (49 tests)

Tests for `api/api_keys_helper.php` - security-critical encryption and API key management:

#### Encryption/Decryption (13 tests)
- ✅ Basic encryption/decryption roundtrip
- ✅ IV uniqueness (same plaintext → different ciphertexts)
- ✅ Empty string handling
- ✅ Long string handling (10,000 chars)
- ✅ Special characters preservation
- ✅ Unicode character preservation
- ✅ Invalid ciphertext handling
- ✅ Short ciphertext validation
- ✅ Corrupted data handling
- ✅ Fallback behavior without encryption key
- ✅ Various data types (JSON, multiline, etc.)

#### Encryption Key Management (2 tests)
- ✅ Retrieving existing encryption key from database
- ✅ Handling missing encryption_config table

#### API Configuration (8 tests)
- ✅ Loading config from file (valid/invalid providers)
- ✅ Config file caching
- ✅ Database fallback when tables don't exist
- ✅ Global API keys loading
- ✅ Specific API key retrieval
- ✅ Bulk config loading for all providers
- ✅ Invalid key name handling

#### Provider Status (6 tests)
- ✅ Provider enabled/disabled checks
- ✅ Default behavior when provider_status table missing
- ✅ Nonexistent provider handling
- ✅ Getting list of enabled providers
- ✅ Filtering disabled providers

#### Model Status (3 tests)
- ✅ Model enabled/disabled checks
- ✅ Default behavior when models_status table missing

#### Data Provider Tests (17 tests)
- ✅ All 12 providers config loading
- ✅ Various encryption data types

## Key Security Features Tested

1. **AES-256-CBC Encryption**
   - Proper IV generation (16 bytes random)
   - IV uniqueness per encryption
   - Proper key length (32 bytes)
   - Base64 encoding of ciphertext

2. **Input Validation**
   - Empty string handling
   - Invalid ciphertext rejection
   - Short/corrupted data handling
   - Unicode and special character preservation

3. **Fallback Mechanisms**
   - Database → config.php fallback
   - Missing table handling
   - PDO exception handling

## Important Notes

### Static Caching Behavior

The functions in `api_keys_helper.php` use static caching for performance. This means:

- Encryption key is cached after first retrieval
- API configs are cached per provider/user
- In tests, cached values may persist across test methods
- Some test assertions account for this caching behavior

### Test Isolation

Tests use PHPUnit mocks for PDO objects to avoid requiring a real database connection. This makes tests:

- Fast (no DB I/O)
- Isolated (no external dependencies)
- Repeatable (consistent results)

## Adding New Tests

### 1. Create Test File

```php
<?php

namespace Tests\Unit\YourNamespace;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class YourTest extends TestCase
{
    #[Test]
    public function testYourFeature(): void
    {
        // Arrange
        $expected = 'value';

        // Act
        $actual = yourFunction();

        // Assert
        $this->assertEquals($expected, $actual);
    }
}
```

### 2. Use Data Providers for Multiple Scenarios

```php
public static function provideTestCases(): array
{
    return [
        'case1' => ['input1', 'expected1'],
        'case2' => ['input2', 'expected2'],
    ];
}

#[Test]
#[DataProvider('provideTestCases')]
public function testWithData($input, $expected): void
{
    $this->assertEquals($expected, yourFunction($input));
}
```

### 3. Run Your Tests

```bash
./vendor/bin/phpunit tests/Unit/YourNamespace/YourTest.php
```

## CI/CD Integration

To integrate with CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Install dependencies
  run: composer install --no-interaction

- name: Run tests
  run: ./vendor/bin/phpunit --testdox

- name: Generate coverage
  run: ./vendor/bin/phpunit --coverage-clover coverage.xml
```

## Future Test Plans

- [ ] Integration tests for `api/streamApi.php`
- [ ] Tests for rate limiting (`api/rate_limiter.php`)
- [ ] Tests for document parsing (`api/document_parser.php`)
- [ ] Tests for message formatting (`api/helpers.php`)
- [ ] Frontend JavaScript tests (Jest)
- [ ] End-to-end tests for complete workflows

## Contributing

When adding new features:

1. ✅ Write tests first (TDD approach recommended)
2. ✅ Ensure all tests pass before committing
3. ✅ Maintain test coverage above 80%
4. ✅ Document test purpose in docblocks
5. ✅ Use descriptive test method names

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://phpunit.de/manual/current/en/writing-tests-for-phpunit.html)
- [Mocking in PHPUnit](https://phpunit.de/manual/current/en/test-doubles.html)
