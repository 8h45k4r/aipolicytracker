import { Link, usePage } from "@inertiajs/react";
import React from "react";

export default function Footer() {
    const { site } = usePage().props;
    const links = site?.links ?? {};
    const year = new Date().getFullYear();

    return (
        <div className="px-6 grid row-auto md:flex items-center justify-between bg-primary w-full py-4 md:px-[64px]">
            <h1 className="order-2 md:order-1 text-sm text-white mt-2 md:mt-0">
                AI Policy Tracker © {year}
            </h1>

            <div className="flex gap-3 ">
                <Link
                    href={route("aboutus.aipolicy")}
                    className="text-white order-1 md:order-2"
                >
                    About (AI) Policy
                </Link>
                {links.privacy_policy && (
                    <a
                        href={links.privacy_policy}
                        className="text-white order-1 md:order-2"
                    >
                        / Privacy Policy
                    </a>
                )}
                {links.terms_of_use && (
                    <a
                        href={links.terms_of_use}
                        className="text-white order-1 md:order-2"
                    >
                        / Terms Of Use
                    </a>
                )}
            </div>
        </div>
    );
}
