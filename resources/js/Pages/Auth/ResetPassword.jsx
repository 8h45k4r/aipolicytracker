import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import { Head, useForm } from "@inertiajs/react";

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: "",
        password_confirmation: "",
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("password.store"), {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reset password" />

            <div className="auth-card">
                <h1 className="text-xl font-bold text-brand-navy">
                    Choose a new password
                </h1>

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
                            onChange={(e) => setData("email", e.target.value)}
                        />
                        <InputError message={errors.email} className="mt-1" />
                    </div>

                    <div>
                        <label htmlFor="password" className="auth-label">
                            New password
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="auth-input mt-1"
                            autoComplete="new-password"
                            autoFocus
                            onChange={(e) => setData("password", e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>

                    <div>
                        <label htmlFor="password_confirmation" className="auth-label">
                            Confirm new password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="auth-input mt-1"
                            autoComplete="new-password"
                            onChange={(e) => setData("password_confirmation", e.target.value)}
                        />
                        <InputError message={errors.password_confirmation} className="mt-1" />
                    </div>

                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing ? "Resetting…" : "Reset password"}
                    </button>
                </form>
            </div>
        </GuestLayout>
    );
}
