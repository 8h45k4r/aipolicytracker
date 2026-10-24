import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, useForm } from "@inertiajs/react";

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route("verification.send"));
    };

    return (
        <GuestLayout>
            <Head title="Verify your email" />

            <div className="auth-card">
                <h1 className="text-xl font-bold text-brand-navy">
                    Verify your email
                </h1>

                {status === "verification-link-sent" ? (
                    <div className="mt-4 rounded-md bg-state-goodbg px-3 py-2 text-sm font-medium text-state-good">
                        A new verification link has been sent to the email address
                        you provided during registration.
                    </div>
                ) : (
                    <p className="mt-2 text-sm text-brand-body">
                        Thanks for signing up! Before getting started, could you
                        verify your email address by clicking on the link we just
                        emailed to you? If you didn&rsquo;t receive the email, we
                        will gladly send you another.
                    </p>
                )}

                <form onSubmit={submit} className="mt-6">
                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing
                            ? "Sending…"
                            : "Resend verification email"}
                    </button>
                </form>

                <p className="mt-4 text-center text-sm text-brand-muted">
                    <Link
                        href={route("logout")}
                        method="post"
                        as="button"
                        className="auth-link"
                    >
                        Sign out
                    </Link>
                </p>
            </div>
        </GuestLayout>
    );
}
