# Pre-commit Hook - Auto-formatting

## Why do my files get reformatted on commit?

This project has a **pre-commit hook** that runs **Laravel Pint** automatically before every commit. This ensures all code follows the same formatting style.

---

## Hook location

```
.hooks/pre-commit
```

This script runs automatically when you run `git commit`.

---

## Formatting configuration

### 1. EditorConfig (`.editorconfig`)
Defines basic indentation rules:
- PHP: 4 spaces (previously tabs)
- JavaScript: 2 spaces
- Blade: 4 spaces (previously tabs)

### 2. Prettier (`.prettierrc`)
Defines formatting rules for all files:
- PHP: 4 spaces (previously tabs)
- JS/TS: 4 spaces (previously tabs)
- JSON: 4 spaces (previously tabs)
- CSS/SCSS: 4 spaces (previously tabs)

### 3. Laravel Pint (`pint.json`)
Laravel-specific PHP formatter. Respects `.editorconfig` rules.

---

## Option 1: Disable the hook (not recommended)

### Disable permanently:
```bash
mv .hooks/pre-commit .hooks/pre-commit.disabled
```

### Disable for a single commit:
```bash
git commit --no-verify -m "your message"
```

---

## Option 2: Work with the hook (recommended)

### Step 1: Configure your editor

**VSCode/Cursor** (`.vscode/settings.json`):
```json
{
  "editor.formatOnSave": true,
  "editor.insertSpaces": true,
  "editor.tabSize": 4,
  "[php]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 4,
    "editor.defaultFormatter": "open-southeners.laravel-pint"
  },
  "[javascript]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 2
  },
  "[blade]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 4
  }
}
```

### Step 2: Install the EditorConfig extension
- VSCode: `editorconfig.editorconfig`
- This extension reads `.editorconfig` automatically

### Step 3: Format before commit (optional)
```bash
# Format all modified PHP files
vendor/bin/pint

# Format a specific file
vendor/bin/pint path/to/file.php

# Preview changes without applying them
vendor/bin/pint --test
```

---

## How the hook works

1. You run `git add` and `git commit`
2. The hook detects staged PHP files
3. It runs Laravel Pint on those files
4. It reformats according to `.editorconfig` + `pint.json`
5. It re-stages the formatted files
6. The commit continues

**Result**: Every commit has consistently formatted code.

---

## Useful commands

```bash
# List files that would be formatted
git diff --cached --name-only --diff-filter=ACM -- '*.php'

# Format manually before commit
vendor/bin/pint $(git diff --cached --name-only --diff-filter=ACM -- '*.php')

# Commit without running the hook (emergencies)
git commit --no-verify -m "message"

# Inspect the hook (if it fails)
cat .git/hooks/pre-commit
```

---

## Benefits of auto-formatting

- **Consistent code**: The whole team uses the same style
- **Fewer conflicts**: No formatting-only changes in PRs
- **No debates**: Tools decide the format
- **Better readability**: Standardized code is easier to read
- **Happier CI/CD**: Formatting checks always pass

---

## Customize formatting rules

### Switch from spaces to tabs:
```bash
# .editorconfig
[*.php]
indent_style = tab  # space or tab
indent_size = 4
```

### Adjust Prettier:
```json
// .prettierrc
{
  "useTabs": false,  // true for tabs
  "tabWidth": 4,
  "printWidth": 120
}
```

### Adjust Pint:
```json
// pint.json
{
  "preset": "laravel",
  "rules": {
    "indentation_type": {
      "type": "space"  // or "tab"
    }
  }
}
```

---

## Troubleshooting

### The hook does not run:
```bash
# Verify the hook exists and is executable
ls -la .hooks/pre-commit
chmod +x .hooks/pre-commit

# Verify Git configuration
git config --local core.hooksPath .hooks
```

### Pint is not installed:
```bash
composer require laravel/pint --dev
```

### The hook fails and I cannot commit:
```bash
# Commit without the hook (temporary)
git commit --no-verify -m "message"

# Run the hook to see the error
.hooks/pre-commit
```

### Different formatting across branches:
```bash
# Reformat the entire codebase
vendor/bin/pint

# Commit the bulk change
git add .
git commit -m "style: apply consistent formatting"
```

---

## More information

- **Laravel Pint**: https://laravel.com/docs/10.x/pint
- **EditorConfig**: https://editorconfig.org/
- **Prettier**: https://prettier.io/
- **Git Hooks**: https://git-scm.com/book/en/v2/Customizing-Git-Git-Hooks

---

## TL;DR

**Problem**: Files are reformatted automatically on commit.

**Cause**: The pre-commit hook runs Laravel Pint.

**Solution**:
1. **Recommended**: Configure your editor to use 4 spaces for PHP
2. **Alternative**: Skip the hook with `git commit --no-verify`
3. **Not recommended**: Delete `.hooks/pre-commit`

**Recent change**: Configuration moved from **tabs** to **spaces** to avoid constant reformatting.
