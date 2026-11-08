<x-layout title="Log in" bare>
    <x-auth-card heading="Log in to RISE" intro="Your private, dated photo record of cracks and surfaces at home.">
        <form action="{{ route('login') }}" method="POST">
            @csrf

            <x-input-field label="Email" name="email" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" autofocus />
            <x-input-field label="Password" name="password" type="password" autocomplete="current-password" />

            <label class="mb-6 flex min-h-11 w-fit cursor-pointer items-center gap-3">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-5 accent-brand">
                Remember me on this device
            </label>

            <button type="submit" class="btn btn-primary w-full">Log in</button>
        </form>

        @if (Route::has('register'))
            <p class="mt-6 text-muted">
                No account yet?
                <a href="{{ route('register') }}" class="link">Create one</a>
            </p>
        @endif
    </x-auth-card>
</x-layout>
