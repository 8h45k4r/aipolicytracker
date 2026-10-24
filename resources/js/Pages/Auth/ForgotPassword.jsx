import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("password.email"));
    };

    return (
        <GuestLayout>
            <Head title="Forgot password" />

            <div className="auth-card">
                <h1 className="text-xl font-bold text-brand-navy">
                    Reset your password
                </h1>
                <p className="mt-2 text-sm text-brand-body">
                    Enter your email address and we will send you a link to
                    choose a new password.
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
                            autoFocus
                            onChange={(e) => setData("email", e.target.value)}
                        />
                        <InputError message={errors.email} className="mt-1" />
                    </div>

                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing ? "Sending…" : "Send reset link"}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-brand-muted">
                    <Link href={route("login")} className="auth-link">
                        Back to sign in
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
