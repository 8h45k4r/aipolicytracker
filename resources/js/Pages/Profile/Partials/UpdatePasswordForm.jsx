import { useRef } from 'react';
import InputError from '@/Components/InputError';
import { useForm } from '@inertiajs/react';
import { Transition } from '@headlessui/react';

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInputRef = useRef();
    const currentPasswordInputRef = useRef();

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInputRef.current.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInputRef.current.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-semibold text-brand-navy">Update password</h2>
                <p className="mt-1 text-sm text-brand-muted">
                    Use a long, random password to keep your account secure.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-6 space-y-4">
                <div>
                    <label htmlFor="current_password" className="auth-label">Current password</label>
                    <input
                        id="current_password"
                        ref={currentPasswordInputRef}
                        value={data.current_password}
                        onChange={(e) => setData('current_password', e.target.value)}
                        type="password"
                        className="auth-input mt-1"
                        autoComplete="current-password"
                    />
                    <InputError message={errors.current_password} className="mt-1" />
                </div>

                <div>
                    <label htmlFor="password" className="auth-label">New password</label>
                    <input
                        id="password"
                        ref={passwordInputRef}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="auth-input mt-1"
                        autoComplete="new-password"
                    />
                    <InputError message={errors.password} className="mt-1" />
                </div>

                <div>
                    <label htmlFor="password_confirmation" className="auth-label">Confirm password</label>
                    <input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        type="password"
                        className="auth-input mt-1"
                        autoComplete="new-password"
                    />
                    <InputError message={errors.password_confirmation} className="mt-1" />
                </div>

                <div className="flex items-center gap-4">
                    <button type="submit" disabled={processing} className="auth-btn !w-auto px-6">
                        Save
                    </button>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-state-good">Saved.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
