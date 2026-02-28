---
title: Artisan Commands
navigation:
  priority: 60
  group: Reference
---

# Artisan Commands

## runpod:sync

Sync files from the local load path to RunPod storage:

```bash
php artisan runpod:sync
php artisan runpod:sync --path=document.pdf
php artisan runpod:sync --path=subdir/
```

## runpod:prune

Prune inactive pods:

```bash
php artisan runpod:prune
php artisan runpod:prune pymupdf
```

## runpod:guardrails

Refresh or clear the guardrails usage cache:

```bash
php artisan runpod:guardrails
php artisan runpod:guardrails --clear
```

## Scheduled Sync

In `routes/console.php`:

```php
Schedule::command('runpod:sync')->everyFiveMinutes();
```
