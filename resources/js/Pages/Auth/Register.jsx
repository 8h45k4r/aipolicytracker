import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import Checkbox from "@/Components/Checkbox";
import { Head, Link, useForm, usePage } from "@inertiajs/react";

export default function Register() {
    const { site } = usePage().props;
    const termsUrl = site?.links?.terms_of_use || route("terms");
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        phone_no: "",
        organization_name: "",
        organization_email: "",
        password: "",
        password_confirmation: "",
        terms_condition: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("register"), {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Create an account" />

            <div className="auth-card !max-w-lg">
                <h1 className="text-xl font-bold text-brand-navy">
                    Create an account
                </h1>
                <p className="mt-1 text-sm text-brand-muted">
                    Join the global AI policy tracking community.
                </p>

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <label htmlFor="name" className="auth-label">
                            Name <span className="text-state-bad">*</span>
                        </label>
                        <input
                            id="name"
                            name="name"
                            value={data.name}
                            className="auth-input mt-1"
                            autoComplete="name"
                            autoFocus
                            onChange={(e) => setData("name", e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>

                    <div>
                        <label htmlFor="email" className="auth-label">
                            Email address <span className="text-state-bad">*</span>
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="auth-input mt-1"
                            autoComplete="username"
                            onChange={(e) => setData("email", e.target.value)}
                            required
                        />
                        <InputError message={errors.email} className="mt-1" />
                    </div>

                    <div>
                        <label htmlFor="phone_no" className="auth-label">
                            Phone number <span className="text-state-bad">*</span>
                        </label>
                        <input
                            id="phone_no"
                            type="tel"
                            name="phone_no"
                            value={data.phone_no}
                            className="auth-input mt-1"
                            autoComplete="tel"
                            onChange={(e) => {
                                setData("phone_no", e.target.value.replace(/\D/g, ""));
                            }}
                            required
                        />
                        <InputError message={errors.phone_no} className="mt-1" />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="organization_name" className="auth-label">
                                Organisation
                            </label>
                            <input
                                id="organization_name"
                                name="organization_name"
                                value={data.organization_name}
                                className="auth-input mt-1"
                                autoComplete="organization"
                                onChange={(e) => setData("organization_name", e.target.value)}
                            />
                            <InputError message={errors.organization_name} className="mt-1" />
                        </div>
                        <div>
                            <label htmlFor="organization_email" className="auth-label">
                                Organisation email
                            </label>
                            <input
                                id="organization_email"
                                type="email"
                                name="organization_email"
                                value={data.organization_email}
                                className="auth-input mt-1"
                                onChange={(e) => setData("organization_email", e.target.value)}
                            />
                            <InputError message={errors.organization_email} className="mt-1" />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="password" className="auth-label">
                                Password <span className="text-state-bad">*</span>
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="auth-input mt-1"
                                autoComplete="new-password"
                                onChange={(e) => setData("password", e.target.value)}
                                required
                            />
                            <InputError message={errors.password} className="mt-1" />
                        </div>
                        <div>
                            <label htmlFor="password_confirmation" className="auth-label">
                                Confirm password <span className="text-state-bad">*</span>
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="auth-input mt-1"
                                autoComplete="new-password"
                                onChange={(e) => setData("password_confirmation", e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            name="terms_condition"
                            checked={data.terms_condition}
                            className="mt-0.5"
                            onChange={(e) => setData("terms_condition", e.target.checked)}
                        />
                        <span className="text-sm text-brand-body">
                            I agree to the{" "}
                            <a
                                href={termsUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="auth-link"
                            >
                                terms and conditions
                            </a>
                        </span>
                    </div>
                    <InputError message={errors.terms_condition} />

                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing ? "Creating account…" : "Create account"}
                    </button>
                </form>

                <p className="mt-6 text-center text-sm text-brand-muted">
                    Already have an account?{" "}
                    <Link href={route("login")} className="auth-link">
                        Sign in
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
