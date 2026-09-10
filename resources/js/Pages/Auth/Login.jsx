import Checkbox from "@/Components/Checkbox";
import GuestLayout from "@/Layouts/GuestLayout";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import ApplicationLogo from "@/Components/ApplicationLogo";
import { Head, Link, useForm } from "@inertiajs/react";
import LogoImage from "@/assets/images/ai_dark_logo.png";
import LogoImageSquare from "@/assets/images/ai-square.png";
import AuthLogo from "@/assets/images/auth/login.png";
import AuthLoginImage from "@/assets/images/auth/loginpage.jpg";
import Responsive from "@/Components/Responsive/Responsive";
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
                                        Welcom back!
                                    </h3>

                                    {status && (
                                        <div className="mb-4 font-medium text-sm text-green-600">
                                            {status}
                                        </div>
                                    )}
                                    <form
                                        onSubmit={submit}
                                        className="flex flex-col space-y-5"
                                    >
                                        <div className="flex flex-col space-y-1">
                                            <label
                                                htmlFor="email"
                                                className="text-sm font-semibold text-gray-500"
                                            >
                                                Email address
                                            </label>
                                            <TextInput
                                                id="email"
                                                type="email"
                                                name="email"
                                                value={data.email}
                                                className="px-4 py-2 transition duration-300 border border-gray-300 rounded focus:border-transparent focus:outline-none focus:ring-4 focus:ring-blue-200"
                                                autoComplete="username"
                                                isFocused={true}
                                                onChange={(e) =>
                                                    setData(
                                                        "email",
                                                        e.target.value
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.email}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div className="flex flex-col space-y-1">
                                            <div className="flex items-center justify-between">
                                                <label
                                                    htmlFor="password"
                                                    className="text-sm font-semibold text-gray-500"
                                                >
                                                    Password
                                                </label>
                                                {/* {canResetPassword && (
                                                <Link
                                                    href={route("password.request")}
                                                    className="text-sm text-blue-600 hover:underline focus:text-blue-800"
                                                >
                                                    Forgot your password?
                                                </Link>
                                            )} */}
                                            </div>
                                            <TextInput
                                                id="password"
                                                type="password"
                                                name="password"
                                                value={data.password}
                                                className="px-4 py-2 transition duration-300 border border-gray-300 rounded focus:border-transparent focus:outline-none focus:ring-4 focus:ring-blue-200"
                                                autoComplete="current-password"
                                                onChange={(e) =>
                                                    setData(
                                                        "password",
                                                        e.target.value
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.password}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div className="flex justify-between">
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    name="remember"
                                                    className="w-4 h-4 transition duration-300 rounded focus:ring-2 focus:ring-offset-0 focus:outline-none focus:ring-blue-200"
                                                    checked={data.remember}
                                                    onChange={(e) =>
                                                        setData(
                                                            "remember",
                                                            e.target.checked
                                                        )
                                                    }
                                                />

                                                <label
                                                    htmlFor="remember"
                                                    className="text-sm font-semibold text-gray-500"
                                                >
                                                    Remember me
                                                </label>
                                            </div>
                                            <Link
                                                href={route("register")}
                                                className="text-sm underline font-semibold text-primary-light hover:text-blue-900"
                                            >
                                                Register
                                            </Link>
                                        </div>
                                        <div>
                                            <button
                                                type="submit"
                                                className="w-full px-4 py-2 text-lg font-semibold text-white transition-colors duration-300 bg-primary-light hover:bg-blue-900 rounded-md shadow focus:outline-none focus:ring-blue-200 focus:ring-4"
                                            >
                                                {processing
                                                    ? "Sign in..."
                                                    : "Sign in"}
                                            </button>
                                        </div>
                                        <div className="mt-3 text-center">
                                            <Link
                                                href="/"
                                                className="text-sm underline font-semibold text-primary-light hover:text-blue-900"
                                            >
                                                Go to home
                                            </Link>
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
