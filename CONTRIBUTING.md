# Contributing Guidelines for endereco-oxid7-client

## General Requirements

- **Repository Structure**: OXID eShop 7 module compatible with
  versions 7.0.0 and higher (tested on 7.0, 7.1 and 7.2).
- **Single `master` branch** supports all OXID 7 versions.
- **PHP 8.0+ compatibility** required.
- The frontend integration relies on Twig templates (OXID 7 replaced
  Smarty with Twig).
- All changes are submitted via pull request after forking.
- Feature branches are created from `master`.

## Development Environment Setup

Refer to the README.MD "Development Setup" section for detailed
instructions. A working OXID 7 shop (e.g. via DDEV) with PHP 8.x and
Node.js 18+ is required to run the full quality suite.

## Code Style and Formatting

The project adheres to **PSR-12 coding standards** for PHP and uses
**ESLint** (flat config, `eslint.config.mjs`) for `endereco.js`.

Required quality checks:

- PHP_CodeSniffer (PSR-12 enforcement)
- PHPStan (static analysis, see `phpstan.7.1.neon`, `phpstan.7.2.neon`,
  `phpstan.7.3.neon`)
- PHPMD (code quality assessment, `check_phpmd.sh`)
- PHP Compatibility checker (`test_php_versions.sh`)
- ESLint (JavaScript linting, `npm run lint`)

Commands:

```
composer qa       # All checks (phpcs, phpmd, phpstan, phpcompat, lint)
composer phpcs    # PHP style only
composer phpcbf   # Auto-fix PHP style issues
npm run lint      # JavaScript linting only
```

> **Note:** ESLint 9 requires Node.js 18+. The bundled DDEV web
> container ships a compatible Node version; when running `npm run lint`
> directly on the host make sure your active Node version is 18 or
> newer (`structuredClone is not defined` indicates a too-old runtime).

## Code Quality Standards

Every commit must satisfy:

1. Leave the codebase in a stable, working state.
2. Pass `composer qa` without errors.
3. Keep the module installable/uninstallable with each commit.
4. Be tested across OXID 7.0–7.2 versions.
5. Maintain existing functionality integrity.

## Commit Message Guidelines

### Structure

**Title**: Brief imperative description under 70 characters.

**Body**:

- Explains the "WHY" and provides reviewer context.
- References relevant issues/meetings (e.g. `DEV-399`).
- Lines wrapped at ~70 characters.
- Keep it concise; split large, unrelated work into multiple commits.

### Title Requirements

- Use imperative mood ("Add feature", not "Added feature").
- Answer "WHAT" — summarize the commit purpose.
- Under 70 characters.
- No prefixes like "fix:" and no issue numbers in the title.

### Body Requirements

- Explain "WHY" — provide contextual reasoning.
- Reference specific issues, emails, or meetings.
- Professional, neutral tone.
- Wrap lines at ~70 characters.
- Include implementation details only when needed for comprehension.

### Example

```
Add keyboard navigation support for autocomplete dropdowns

Enable users to navigate suggestions via keyboard inputs,
improving accessibility compliance. Users navigate with Tab,
Enter, and arrow keys without requiring mouse input.

This addresses WCAG 2.1 guidelines and EAA compatibility
requirements documented in DEV-789.
```

### What to Avoid

- Vague titles ("fixed stuff", "updates").
- Multiple unrelated changes per commit.
- Missing context/reasoning.
- Unprofessional language.
- Lines exceeding ~70 characters.
- Mixing change types (bug fixes + features + refactoring).
- Fixing previous commits (use `git commit --amend` instead).

## Version-Specific Considerations

**PHP**: Ensure PHP 8.0+ compatibility; verify against the minimum
supported version.

**OXID eShop 7**: Test across versions 7.0–7.2 and validate the Twig
template extensions and the admin module settings.

**JavaScript**: Lint `endereco.js` with ESLint; run on Node.js 18+.

## Pull Request Requirements

Before submission:

- ✅ Commits follow the message guidelines.
- ✅ `composer qa` passes.
- ✅ `npm run lint` passes.
- ✅ Module installs/uninstalls successfully.
- ✅ Feature branch created from `master`.
- ✅ OXID 7 version compatibility tested (7.0–7.2).
- ✅ PHP compatibility verified (8.0+).

## Quality Checklist

- Clear, imperative title under 70 characters.
- Body explains the business context.
- Professional language throughout.
- Lines broken at ~70 characters.
- Issue/meeting references included.
- Code passes `composer qa` and `npm run lint`.
- Installation/uninstallation functions.
- Changes logically grouped.
- No fixes for previous commits appended.
- OXID 7 compatibility tested across supported versions.

## Getting Help

Request clarification in issue comments before beginning work.
