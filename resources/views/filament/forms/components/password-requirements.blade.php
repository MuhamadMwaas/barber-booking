@once
    <style>
        /* Hallmark · pre-emit critique: P5 H5 E5 S5 R5 V5 */
        /* Hallmark · component: password requirements · genre: modern-minimal · theme: Filament
         * states: unmet (hollow danger ring) · met (filled success check)
         * contrast: labels stay on the neutral ink token in both states, so the
         *           status is carried by the indicator rather than by low-contrast text
         */
        /* Filament lays below-content children out as a wrapping flex row, so a
           helperText() set by the caller would sit beside the checklist rather
           than under it. Claim a full row for the checklist. */
        .fi-sc.fi-inline > :has(.password-requirements) {
            flex: 1 0 100%;
        }

        .password-requirements {
            --password-requirements-border: var(--success-600);
            --password-requirements-ink: var(--gray-700);
            --password-requirements-title: var(--gray-950);
            --password-requirements-success: var(--success-600);
            --password-requirements-danger: var(--danger-500);
            --password-requirements-check: white;

            width: 100%;
            margin-block-start: 0.5rem;
            padding: 0.875rem 1rem;
            color: var(--password-requirements-ink);
            border: 1px solid var(--password-requirements-border);
            border-radius: 0.75rem;
        }

        .dark .password-requirements {
            --password-requirements-border: var(--success-500);
            --password-requirements-ink: var(--gray-300);
            --password-requirements-title: var(--gray-50);
            --password-requirements-success: var(--success-500);
            --password-requirements-danger: var(--danger-400);
            --password-requirements-check: var(--gray-950);
        }

        .password-requirements__title {
            margin: 0 0 0.625rem;
            color: var(--password-requirements-title);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .password-requirements__list {
            display: grid;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .password-requirements__item {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            min-width: 0;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .password-requirements__indicator {
            display: inline-grid;
            flex: 0 0 1.25rem;
            width: 1.25rem;
            height: 1.25rem;
            color: transparent;
            border: 2px solid var(--password-requirements-danger);
            border-radius: 9999px;
            place-items: center;
            transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }

        .password-requirements__item.is-met .password-requirements__indicator {
            color: var(--password-requirements-check);
            background-color: var(--password-requirements-success);
            border-color: var(--password-requirements-success);
        }

        .password-requirements__check {
            width: 0.75rem;
            height: 0.75rem;
        }

        @media (prefers-reduced-motion: reduce) {
            .password-requirements__indicator {
                transition: none;
            }
        }
    </style>
@endonce

<div
    class="password-requirements"
    x-data="{
        password: '',
        inputId: @js($inputId),
        minimumLength: @js($minimumLength),
        init() {
            // Livewire can morph this panel back in while the field already holds
            // a value, so read the input rather than waiting for a keystroke.
            this.password = document.getElementById(this.inputId)?.value ?? ''
        },
        hasMinimumLength() { return Array.from(this.password).length >= this.minimumLength },
        hasLatinCharactersOnly() { return this.password.length > 0 && /^[\x21-\x7E]+$/.test(this.password) },
        hasUppercase() { return /[A-Z]/.test(this.password) },
        hasNumber() { return /[0-9]/.test(this.password) },
    }"
    x-on:password-requirements-updated.window="
        if ($event.detail.id === inputId) password = String($event.detail.value ?? '')
    "
>
    <p class="password-requirements__title">
        {{ __('passwords.requirements.title') }}
    </p>

    <ul class="password-requirements__list" aria-live="polite" aria-atomic="true">
        @foreach ([
            'hasMinimumLength()' => __('passwords.requirements.minimum_length', ['min' => $minimumLength]),
            'hasLatinCharactersOnly()' => __('passwords.requirements.latin_only'),
            'hasUppercase()' => __('passwords.requirements.uppercase'),
            'hasNumber()' => __('passwords.requirements.number'),
        ] as $condition => $label)
            <li
                class="password-requirements__item"
                x-bind:class="{ 'is-met': {{ $condition }} }"
            >
                <span class="password-requirements__indicator" aria-hidden="true">
                    <svg
                        class="password-requirements__check"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m5 10 3 3 7-7" />
                    </svg>
                </span>

                <span>{{ $label }}</span>

                <span
                    class="fi-sr-only"
                    x-text="{{ $condition }}
                        ? @js(__('passwords.requirements.state.met'))
                        : @js(__('passwords.requirements.state.unmet'))"
                ></span>
            </li>
        @endforeach
    </ul>
</div>
