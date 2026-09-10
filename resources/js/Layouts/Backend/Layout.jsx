import React, { useEffect } from "react";
import SiderBar from "./Partials/SiderBar";
import Header from "./Partials/Header";
import { usePage } from "@inertiajs/react";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";

// Helper function to generate avatar URL
const generateAvatarUrl = (name) => {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(
        name
    )}&background=EBF4FF&color=7F9CF5`;
};

export default function Layout({ children }) {
    const { props } = usePage();
    const userName = props.auth.user ? props.auth.user.name : "Guest"; // Get the user's name or use 'Guest' if not available

    const avatarUrl = generateAvatarUrl(userName);

    // Extract flash messages
    const successMessage = props.flash?.success;
    const errorMessage = props.flash?.error;

    // Show toast notifications
    useEffect(() => {
        if (successMessage) {
            toast.success(successMessage);
        }
        if (errorMessage) {
            toast.error(errorMessage);
        }
    }, [successMessage, errorMessage]);

    return (
        <div>
            {/* Navigation for mobile */}
            <button
                data-drawer-target="sidebar-multi-level-sidebar"
                data-drawer-toggle="sidebar-multi-level-sidebar"
                aria-controls="sidebar-multi-level-sidebar"
                type="button"
                className="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600"
            >
                <span className="sr-only">Open sidebar</span>
                <svg
                    className="w-6 h-6"
                    aria-hidden="true"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        clipRule="evenodd"
                        fillRule="evenodd"
                        d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"
                    ></path>
                </svg>
            </button>

            {/* Sidebar */}
            <SiderBar />

            {/* Content */}
            <div className="p-4 sm:ml-64">
                <Header avatarUrl={avatarUrl} userName={userName} />
                <div className="rounded-lg mt-0 md:mt-20">{children}</div>
            </div>

            {/* ToastContainer for toast notifications */}
            <ToastContainer />
        </div>
    );
}
