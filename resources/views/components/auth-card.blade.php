<div class="flex min-h-screen flex-col items-center justify-center bg-[var(--color-app-bg)] px-4 py-6 sm:pt-0">
    <div>
        {{ $logo }}
    </div>

    <div class="ui-card mt-6 w-full overflow-hidden px-6 py-5 sm:max-w-md">
        {{ $slot }}
    </div>
</div>
