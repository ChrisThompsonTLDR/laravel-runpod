@extends('hyde::layouts.app')

@section('content')
<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        {{-- Hero --}}
        <div class="text-center mb-16">
            <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl md:text-6xl">
                Laravel RunPod
            </h1>
            <p class="mt-4 text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Laravel integration for the RunPod REST API and S3-compatible network volume storage. A fluent interface for pods, serverless endpoints, storage, and more.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="docs/installation.html" class="inline-flex items-center px-6 py-3 rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    Get Started
                </a>
                <a href="https://github.com/christhompsontldr/laravel-runpod" target="_blank" rel="noopener" class="inline-flex items-center px-6 py-3 rounded-lg font-medium text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    View on GitHub
                </a>
            </div>
        </div>

        {{-- Capability Cards --}}
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <a href="docs/pods.html" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Pods</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Persistent GPU instances with lifecycle management. Start, stop, restart, and manage pods programmatically.</p>
            </a>
            <a href="docs/serverless.html" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Serverless</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Endpoints with auto-scaling workers. Built-in idle timeout for cost-effective on-demand workloads.</p>
            </a>
            <a href="docs/storage.html" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Storage</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">S3-compatible network volumes. Sync files, ensure presence, and manage data with the fluent disk API.</p>
            </a>
            <a href="docs/installation.html#templates" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Templates</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Container templates for consistent deployments. Create and manage reusable pod configurations.</p>
            </a>
            <a href="docs/guardrails.html" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Guardrails</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Usage limits for pods, serverless, and storage. Avoid unexpected spend with configurable thresholds.</p>
            </a>
            <a href="docs/artisan-commands.html" class="block p-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow hover:shadow-lg transition-shadow">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Artisan Commands</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-300">runpod:sync, runpod:prune, runpod:guardrails. Integrate with your Laravel workflow.</p>
            </a>
        </div>
    </div>
</main>
@endsection
