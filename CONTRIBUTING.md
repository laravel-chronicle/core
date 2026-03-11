# Contributing to Chronicle

Thank you for your interest in contributing to **Chronicle**.

Chronicle is an open-source **append-only audit ledger for Laravel** designed to provide **tamper-detectable audit logging** through cryptographic hashing and verifiable datasets.

Contributions of all kinds are welcome, including:

- bug fixes
- new features
- documentation improvements
- test improvements
- developer tooling

---

# Project Philosophy

Chronicle is built around several core principles:

### Append-only

Entries are immutable once recorded.

There is no update or delete path in the core API.

### Explicit intent

Every entry must include:

- actor
- action
- subject

Chronicle avoids automatic logging and hidden behavior.

### Stable contracts

Export formats and data structures must remain stable.

Breaking changes require a major version bump.

### Low magic

Chronicle avoids implicit framework hooks or observers.

Entries are recorded explicitly by the developer.

### Deterministic behavior

Ledger operations must always produce the same result for the same input.

---

# Getting Started

Fork the repository

Install dependencies:

```bash
composer install
```

Run the test suite:
```bash
./vendor/bin/pest
```

Format code:

```bash
./vendor/bin/pint
```


---

# Repository Structure

Chronicle is organized by domain:

- Entry/ Entry creation and persistence
- Pipeline/ Entry processing pipeline
- Validation/ Entry validators
- Context/ Context resolvers
- Hashing/ Ledger hashing
- Checkpoints/ Checkpoint creation and signing
- Export/ Dataset export system
- Verification/ Integrity verification tools
- Console/ Artisan commands

Understanding these domains will help you navigate the codebase.

---

# Development Workflow

1. Open or comment on an existing issue
2. Fork the repository
3. Create a feature branch
4. Implement your changes
5. Add tests
6. Submit a Pull Request

Example branch name: `feature/action-validator`

Small, focused pull requests are preferred.

---

# Coding Standards

Chronicle follows Laravel coding conventions.

Formatting is enforced using **Laravel Pint**.

Before submitting a PR, run:

```bash
./vendor/bin/pint
```

---

# Testing

All new functionality should include tests.

Run the test suite using:

```bash
./vendor/bin/pest
```

Tests should cover:

- success scenarios
- validation failures
- edge cases

---

# Pull Request Guidelines

When submitting a PR:

- keep changes focused
- include tests for new functionality
- update documentation when needed
- explain the purpose of the change

Example PR structure:
- Summary
- Changes
- Tests

---

# Good First Issues

If you're new to the project, look for issues labeled: `good first issue`

These tasks are designed to be approachable for new contributors.

Examples include:

- documentation improvements
- small validators
- CLI output improvements
- test additions

---

# Reporting Bugs

If you discover a bug, please open an issue including:

- Chronicle version
- Laravel version
- PHP version
- steps to reproduce the issue
- expected behavior
- actual behavior

Clear reproduction steps help resolve issues faster.

---

# Feature Requests

Feature ideas are welcome.

Before implementing a large change, please open an issue so the design can be discussed first.

This helps ensure that new features align with Chronicle's architecture and philosophy.

---

# Code of Conduct

Please be respectful and constructive when participating in discussions.

Open source thrives when contributors collaborate in a positive environment.

---

# Recognition

All contributors are appreciated.

Thank you for helping improve **Chronicle**.
