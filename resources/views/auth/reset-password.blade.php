<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="ui-auth-logo" />
            </a>
        </x-slot>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="ui-auth-status" :errors="$errors" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email')" />

                <x-input id="email"  type="email" name="email" :value="old('email', $request->email)" required autofocus />
            </div>

            <!-- Password -->
            <div class="ui-field-gap">
                <x-label for="password" :value="__('Password')" />

                <x-input id="password"  type="password" name="password" required />
            </div>

            <!-- Confirm Password -->
            <div class="ui-field-gap">
                <x-label for="password_confirmation" :value="__('Confirm Password')" />

                <x-input id="password_confirmation" 
                                    type="password"
                                    name="password_confirmation" required />
            </div>

            <div class="ui-auth-actions">
                <x-button>
                    {{ __('Reset Password') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
