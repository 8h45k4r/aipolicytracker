import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import { Head, useForm } from "@inertiajs/react";

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: "",
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("password.confirm"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm your password" />

            <div className="auth-card">
                <h1 className="text-xl font-bold text-brand-navy">
                    Confirm your password
                </h1>
                <p className="mt-2 text-sm text-brand-body">
                    This is a secure area. Please confirm your password before
                    continuing.
                </p>

                <form onSubmit={submit} className="mt-6 space-y-4">
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
                            autoFocus
                            onChange={(e) => setData("password", e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>

                    <button type="submit" disabled={processing} className="auth-btn">
                        {processing ? "Confirming…" : "Confirm"}
                    </button>
                </form>
            </div>
        </GuestLayout>
    );
}
