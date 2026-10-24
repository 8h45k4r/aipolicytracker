import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import Checkbox from "@/Components/Checkbox";
import { Head, Link, useForm } from "@inertiajs/react";

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("login"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Sign in" />

            <div className="auth-card">
                <h1 className="text-xl font-bold text-brand-navy">
                    Sign in to your account
                </h1>
                <p className="mt-1 text-sm text-brand-muted">
                    Track AI policy developments worldwide.
                </p>

                {status && (
                    <div className="mt-4 rounded-md bg-state-goodbg px-3 py-2 text-sm font-medium text-state-good">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <label htmlFor="email" className="auth-label">
                            Email address
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="auth-input mt-1"
                            autoComplete="username"
                            autoFocus
                            onChange={(e) => setData("email", e.target.value)}
                        />
                        <InputError message={errors.email} className="mt-1" />
                    </div>

                    <div>
                        <label htmlFor="password" className="auth-label">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="auth-input mt-1"
                            autoComplete="current-password"
                            onChange={(e) => setData("password", e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>

                    <div className="flex items-center justify-between">
                        <label className="flex items-center gap-2">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData("remember", e.target.checked)}
                            />
                            <span className="text-sm text-brand-body">
                                Remember me
                            </span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route("password.request")}
                                className="auth-link"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing ? "Signing in…" : "Sign in"}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-brand-muted">
                    Don&rsquo;t have an account?{" "}
                    <Link href={route("register")} className="auth-link">
                        Create one
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
