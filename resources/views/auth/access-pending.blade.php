@extends('layouts.guest')

@section('title', 'Teacher access request received')

@section('body')
    <x-partials.authentication-card
        title="Request received"
        description="Your verified TrueLearners account has been submitted for teacher access review."
    >
        <div class="space-y-4 text-sm text-muted-foreground">
            <p>Your request is pending. A Skuul administrator must review and approve it before you can use teacher features.</p>
            <p>Signing in with a TrueLearners email does not automatically create an account or give you a role.</p>
            <a href="{{ route('login') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-border px-4 py-2 font-medium text-foreground transition hover:bg-muted">
                Back to login
            </a>
        </div>
    </x-partials.authentication-card>
@endsection
