@extends('layouts.app', ['breadcrumbs' => [
    ['href' => route('dashboard'), 'text' => 'Dashboard'],
    ['href' => route('schools.settings'), 'text' => 'School setup'],
    ['text' => 'Moodle integration', 'active'],
]])

@section('title', 'Moodle integration')
@section('page_heading', 'Moodle integration')

@section('content')
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <div>
            <p class="text-sm text-muted-foreground">Connect this school to Moodle. The connection is read-only: Skuul will only read course and enrollment information from Moodle.</p>
        </div>

        @if ($errors->has('moodle'))
            <div role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">{{ $errors->first('moodle') }}</div>
        @endif

        <section class="rounded-xl border bg-card p-5">
            <form method="POST" action="{{ route('integrations.moodle.update') }}" class="flex flex-col gap-5">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-2">
                    <label for="moodle_base_url" class="text-sm font-medium">Moodle site URL</label>
                    <input id="moodle_base_url" name="moodle_base_url" type="url" required value="{{ old('moodle_base_url', $integration->moodle_base_url ?: config('services.moodle.base_url')) }}" placeholder="https://your-school.org" class="h-11 rounded-lg border bg-background px-3 text-sm">
                    @error('moodle_base_url') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="moodle_token" class="text-sm font-medium">Moodle web service token</label>
                    <input id="moodle_token" name="moodle_token" type="password" autocomplete="new-password" value="" placeholder="{{ $integration->exists && filled($integration->moodle_token) ? 'Saved securely — leave blank to keep it' : 'Paste your Moodle token' }}" class="h-11 rounded-lg border bg-background px-3 text-sm">
                    <p class="text-xs text-muted-foreground">The token is encrypted before it is saved. It is never shown again on this page.</p>
                    @error('moodle_token') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" name="moodle_enabled" value="1" @checked(old('moodle_enabled', $integration->moodle_enabled)) class="size-4 rounded border">
                    Enable Moodle integration for this school
                </label>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground">Save settings</button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border bg-card p-5">
            <h2 class="font-semibold">Connection test</h2>
            <p class="mt-1 text-sm text-muted-foreground">Test whether Skuul can reach Moodle and read its site information.</p>
            @if ($integration->moodle_last_tested_at)
                <p class="mt-2 text-xs text-muted-foreground">Last successful test: {{ $integration->moodle_last_tested_at->format('M j, Y g:i A') }}</p>
            @endif
            <form method="POST" action="{{ route('integrations.moodle.test') }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg border px-5 text-sm font-medium hover:bg-muted" @disabled(!$integration->exists || blank($integration->moodle_token))>Test connection</button>
            </form>
        </section>
    </div>
@endsection
