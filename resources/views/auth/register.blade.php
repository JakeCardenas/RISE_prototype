<x-layout title="Register" bare>
    <x-auth-card heading="Create your account" intro="Start a private photo record of cracks at home.">
        <form action="{{ route('register') }}" method="POST">
            @csrf

            <x-input-field label="Name" name="name" autocomplete="name" autofocus />
            <x-input-field label="Email" name="email" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" />
            <x-input-field label="Password" name="password" type="password" autocomplete="new-password" hint="At least 8 characters." />
            <x-input-field label="Confirm password" name="password_confirmation" type="password" autocomplete="new-password" />

            <button type="submit" class="btn btn-primary mt-1 w-full">Create account</button>
        </form>

        @if (Route::has('login'))
            <p class="mt-6 text-muted">
                Already registered?
                <a href="{{ route('login') }}" class="link">Sign in</a>
            </p>
        @endif
    </x-auth-card>
</x-layout>
