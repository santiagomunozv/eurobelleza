<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="ui-auth-logo" />
            </a>
        </x-slot>

        <!-- Session Status -->
        <x-auth-session-status class="ui-auth-status" :status="session('status')" />

        <!-- Validation Errors -->
        <x-auth-validation-errors class="ui-auth-status" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email')" />

                <x-input id="email"  type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <!-- Password -->
            <div class="ui-field-gap">
                <x-label for="password" :value="__('Password')" />

                <x-input id="password" 
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
            </div>

            <div class="ui-auth-actions">
                <x-button class="ml-3">
                    Iniciar sesión
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
