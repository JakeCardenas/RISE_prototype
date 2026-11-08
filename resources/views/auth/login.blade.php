<x-layout title="Sign in" bare>
    <x-auth-card heading="Welcome back" intro="Sign in to see your photos and follow-ups.">
        <form action="{{ route('login') }}" method="POST">
            @csrf

            <x-input-field label="Email" name="email" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" autofocus />
            <x-input-field label="Password" name="password" type="password" autocomplete="current-password" />

            <label class="mb-6 flex min-h-11 w-fit cursor-pointer items-center gap-3">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-5 accent-brand">
                Keep me signed in on this device
            </label>

            <button type="submit" class="btn btn-primary w-full">Sign in</button>
        </form>

        @if (Route::has('register'))
            <p class="mt-6 text-muted">
                No account yet?
                <a href="{{ route('register') }}" class="link">Create one</a>
            </p>
        @endif
    </x-auth-card>
</x-layout>
