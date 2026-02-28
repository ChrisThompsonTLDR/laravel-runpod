---
title: Introduction
navigation:
  priority: 1
  group: Getting Started
---

# Laravel RunPod

Laravel integration for the [RunPod REST API](https://rest.runpod.io/v1) and RunPod S3-compatible network volume storage. Provides a fluent, Laravel-esque interface for managing pods, serverless endpoints, network volumes, templates, container registry auths, billing, and file storage.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Quick Start

```bash
composer require christhompsontldr/laravel-runpod
```

Then head to [Installation](installation) to configure your API keys and storage.

## Capabilities

- **Pods** – Persistent GPU instances with full lifecycle management
- **Serverless** – Endpoints with auto-scaling workers and idle timeout
- **Storage** – S3-compatible network volumes with fluent file sync
- **Guardrails** – Configurable usage limits to control costs
