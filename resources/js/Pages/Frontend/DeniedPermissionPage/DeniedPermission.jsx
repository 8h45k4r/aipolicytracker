import { AppLayout } from "@/Layouts/AppLayout";
import { Head, Link } from "@inertiajs/react";
import React from "react";

export default function DeniedPermission() {
    return (
        <AppLayout>
            <Head title="Denied permission" />
            <div className="content-wrapper  mt-5 xl:mt-0 lg:mt-0 md:relative md:top-[-60px]">
                <div className="flex justify-center">
                    <div className="rounded-md md:bg-white lg:bg-white md:w-1/2 md:px-20 py-10">
                        <div className="w-full mb-5 text-center">
                            <span className="text-center text-sm tracking-wider font-bold">
                                Already registered in (AI) policy tracker?
                            </span>
                            <Link
                                href={route("login")}
                                method="get"
                                className="underline ml-2"
                            >
                                <span className="text-primary-light font-semibold">
                                    Log In
                                </span>
                            </Link>
                        </div>

                        <h1 className="tracking-wider leading-6">
                            <strong className="me-2">
                                Exclusive Access to Comprehensive AI Policy
                                Reports
                            </strong>
                            Upgrade to <strong>Premium</strong> to unlock
                            exclusive insights into global AI regulatory
                            landscapes. Premium users gain access to:
                        </h1>
                        <ul className="mt-5">
                            <li className="flex items-center gap-3 mb-5">
                                <strong>
                                    <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                </strong>
                                <h2 className=" tracking-wider leading-6">
                                    <strong className="me-2">
                                        High-Level AI Regulation Summary:
                                    </strong>
                                    A quick overview of AI regulation status
                                    across countries.
                                </h2>
                            </li>
                            <li className="flex items-center gap-3 mb-5">
                                <strong>
                                    <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                </strong>
                                <h2 className=" tracking-wider leading-6">
                                    <strong className="me-2">
                                        Detailed Market Profiles:
                                    </strong>
                                    In-depth data on AI policies, legislation,
                                    and schedules in individual markets.
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
                                    Receive real-time alerts on important
                                    regulatory changes and upcoming legislation
                                    that could impact your business.
                                </h2>
                            </li>
                            <li className="mb-5">Additionally, enjoy:</li>

                            <li className="flex items-center gap-3 mb-5">
                                <strong>
                                    <i className="fa-solid fa-arrow-right-long text-xl"></i>
                                </strong>
                                <h2 className=" tracking-wider">
                                    <strong className="me-2 leading-6">
                                        AI Policy News:
                                    </strong>
                                    Stay informed with the latest developments
                                    in AI governance from around the world.
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
                                    Access detailed timelines tracking the
                                    progress of AI policies and regulations in
                                    key markets.
                                </h2>
                            </li>
                        </ul>

                        <div className="w-full mt-5">
                            <h2 className="text-center text-xl">
                                Unlock more (AI) policy tracker
                            </h2>

                            <div className="flex justify-center mt-5">
                                <Link
                                    method="get"
                                    href={route("register")}
                                    className=" text-xl px-10 py-2 bg-gray-50 hover:bg-gray-100 rounded-full border uppercase"
                                >
                                    Register{" "}
                                    <i className="fa-solid fa-arrow-right-long ml-2"></i>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
