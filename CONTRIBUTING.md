# Contributing to Laravel Chronicle

Thank you for your interest in contributing to Chronicle.

We welcome:
- bug reports
- documentation improvements
- tests
- feature suggestions
- code contributions

---

# Development Setup

Clone the repository:

```bash
git clone https://github.com/laravel-chronicle/core
```

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

---

# Coding Guidelines

Chronicle follows several important principles:
- explicit over implicit
- deterministic behavior
- append-only guarantees
- minimal hidden magic

When contributing:
- avoid introducing automatic behaviors
- preserve canonical serialization guarantees
- ensure hashing logic remains deterministic

---

# Pull Request Guidelines

Please ensure:
- tests pass
- new functionality includes tests
- documentation is updated
- public APIs are discussed before major changes

---

# Reporting Bugs

Use the bug report template.

Include:
- Chronicle version
- Laravel version
- reproduction steps
- expected vs actual behavior

---

Thank you for helping improve Chronicle.
