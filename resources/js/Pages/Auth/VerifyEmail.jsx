import GuestLayout from "@/Layouts/GuestLayout";
import PrimaryButton from "@/Components/PrimaryButton";
import { Head, Link, useForm } from "@inertiajs/react";
import Responsive from "@/Components/Responsive/Responsive";
import LogoImage from "@/assets/images/ai_dark_logo.png";

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route("verification.send"));
    };

    return (
        // <GuestLayout>
        //     <Head title="Email Verification" />

        //     <div className="mb-4 text-sm text-gray-600">
        //         Thanks for signing up! Before getting started, could you verify
        //         your email address by clicking on the link we just emailed to
        //         you? If you didn't receive the email, we will gladly send you
        //         another.
        //     </div>

        //     {status === "verification-link-sent" && (
        //         <div className="mb-4 font-medium text-sm text-green-600">
        //             A new verification link has been sent to the email address
        //             you provided during registration.
        //         </div>
        //     )}

        //     <form onSubmit={submit}>
        //         <div className="mt-4 flex items-center justify-between">
        //             <PrimaryButton disabled={processing}>
        //                 Resend Verification Email
        //             </PrimaryButton>
        //         </div>
        //     </form>
        //     <Link
        //         href={route("logout")}
        //         className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        //     >
        //         Log Out
        //     </Link>
        // </GuestLayout>

        <GuestLayout>
            <Head title="Log in" />
            <div className="lg:flex items-center lg:min-h-screen bg-gray-100 lg:justify-center">
                <div className="flex flex-col overflow-hidden max md:flex-row md:flex-1 ">
                    {/* logo and image side */}
                    <Responsive responsive={["lg", "xl"]}>
                        <div className="w-[50%] p-4 py-6 text-white md:flex-shrink-0 md:flex md:flex-col md:items-center md:justify-evenly rounded-md h-screen pr-9">
                            {/* <div className=" bg-primary-light w-full h-full flex items-center justify-center rounded-md">
                            <img
                                src={AuthLoginImage}
                                alt="AI Policy Tracker"
                                className="h-full w-full rounded-md"
                            />
                        </div> */}

                            <div className=" bg-primary-light w-full h-full flex items-center justify-center rounded-md px-10">
                                <div>
                                    <h1 className=" text-3xl font-bold text-center">
                                        Welcome Back
                                    </h1>
                                    <ul className="mt-5">
                                        <li className="flex items-center gap-3 mb-5">
                                            <strong>
                                                <i className="fa-solid fa-arrow-right-long text-sm"></i>
                                            </strong>
                                            <h2 className="tracking-wider leading-6">
                                                <strong className="me-2">
                                                    High-Level AI Regulation
                                                    Summary:
                                                </strong>
                                                A quick overview of AI
                                                regulation status across
                                                countries.
                                            </h2>
                                        </li>
                                        <li className="flex items-center gap-3 mb-5">
                                            <strong>
                                                <i className="fa-solid fa-arrow-right-long text-sm"></i>
                                            </strong>
                                            <h2 className=" tracking-wider leading-6">
                                                <strong className="me-2">
                                                    Detailed Market Profiles:
                                                </strong>
                                                In-depth data on AI policies,
                                                legislation, and schedules in
                                                individual markets.
                                            </h2>
                                        </li>
                                        <li className="flex items-center gap-3 mb-5">
                                            <strong>
                                                <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                            </strong>
                                            <h2 className=" tracking-wider">
                                                <strong className="me-2 leading-6">
                                                    Policy Alerts:
                                                </strong>
                                                Receive real-time alerts on
                                                important regulatory changes and
                                                upcoming legislation that could
                                                impact your business.
                                            </h2>
                                        </li>
                                        <li className="mb-5 font-bold">
                                            Additionally, enjoy:
                                        </li>
                                        <li className="flex items-center gap-3 mb-5">
                                            <strong>
                                                <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                            </strong>
                                            <h2 className=" tracking-wider">
                                                <strong className="me-2 leading-6">
                                                    AI Policy News:
                                                </strong>
                                                Stay informed with the latest
                                                developments in AI governance
                                                from around the world.
                                            </h2>
                                        </li>

                                        <li className="flex items-center gap-3 mb-5">
                                            <strong>
                                                <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                            </strong>
                                            <h2 className=" tracking-wider">
                                                <strong className="me-2 leading-6">
                                                    Policy Timelines:
                                                </strong>
                                                Access detailed timelines
                                                tracking the progress of AI
                                                policies and regulations in key
                                                markets.
                                            </h2>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </Responsive>

                    {/* Login side */}
                    <div className="w-full lg:w-[50%]">
                        <div className="w-full">
                            <div className="flex items-center lg:h-screen">
                                <div className="w-full lg:w-[60%] px-5 lg:px-0 mt-6 lg:ml-12 lg:mt-0 ">
                                    <img
                                        src={LogoImage}
                                        alt="AI Policy Tracker"
                                        className="w-[127px] h-[33px]"
                                    />
                                    <h3 className="my-4 font-bold text-xl text-primary-light">
                                        Verify you email
                                    </h3>

                                    {status && (
                                        <div className="mb-4 font-medium text-sm text-green-600">
                                            {status}
                                        </div>
                                    )}
                                    {!(status === "verification-link-sent") && (
                                        <div className="mb-4 text-sm text-gray-600">
                                            Thanks for signing up! Before
                                            getting started, could you verify
                                            your email address by clicking on
                                            the link we just emailed to you? If
                                            you didn't receive the email, we
                                            will gladly send you another.
                                        </div>
                                    )}

                                    {status === "verification-link-sent" && (
                                        <div className="mb-4 font-medium text-sm text-green-600">
                                            A new verification link has been
                                            sent to the email address you
                                            provided during registration.
                                        </div>
                                    )}

                                    <form onSubmit={submit}>
                                        {/* <div className="mt-4 flex items-center justify-between">
                                                <PrimaryButton
                                                    disabled={processing}
                                                >
                                                    Resend Verification Email
                                                </PrimaryButton>
                                            </div> */}
                                        <div>
                                            <button
                                                disabled={processing}
                                                type="submit"
                                                className="w-full px-4 py-2 text-lg font-semibold text-white transition-colors duration-300 bg-primary-light hover:bg-blue-900 rounded-md shadow focus:outline-none focus:ring-blue-200 focus:ring-4"
                                            >
                                                {processing
                                                    ? "Resend Verification Email..."
                                                    : "Resend Verification Email"}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
