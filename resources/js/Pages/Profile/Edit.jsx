import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Head } from '@inertiajs/react';

export default function Edit({ auth, mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-xl font-semibold leading-tight text-brand-navy">Profile</h2>}
        >
            <Head title="Profile" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-brand-line sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-brand-line sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="rounded-lg bg-white p-5 shadow-sm ring-1 ring-brand-line sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
