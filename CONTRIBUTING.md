# Contributing to DeskCaptcha

> 🌐 [desknect.com](https://desknect.com) &nbsp;|&nbsp; 📖 [Official Docs](https://dcaptcha.desknect.com/api-documentacao) &nbsp;|&nbsp; ❤️ [Donate](https://desknect.com/donate)

Thank you for your interest in contributing! This document describes how to get started.

---

## Development Setup

```bash
git clone https://github.com/desknect/deskcaptcha.git
cd deskcaptcha
chmod -R 775 storage/ database/
```

Requirements: PHP 8.0+, GD extension, SQLite3 extension.

---

## Code Standards

- PHP 8.0+ syntax
- PSR-4 autoloading under `DeskCaptcha\` namespace
- 4-space indentation, no trailing whitespace
- `declare(strict_types=1)` in all PHP files
- No external dependencies (pure PHP)

---

## Submitting a Pull Request

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes with tests
4. Ensure the API still responds correctly: `curl http://localhost/v1/health`
5. Push and open a PR against `main`

---

## Reporting Bugs

Use the [GitHub Issues](https://github.com/desknect/deskcaptcha/issues) page.  
Include: PHP version, OS, steps to reproduce, expected vs actual behavior.

---

## License

By contributing, you agree your code will be licensed under the [MIT License](LICENSE).
