import ApplicationLogo from "@/Components/ApplicationLogo";
import { Link } from "@inertiajs/react";
import React, { useState } from "react";

export default function SiderBar() {
    const [openNavBarDropDown, setOpenNavBarDropDown] = useState(false);

    const handleNavBarDropDown = () => {
        setOpenNavBarDropDown(!openNavBarDropDown);
    };
    // current page url set in currentUrl
    const currentUrl = window.location.pathname;

    const isActiveUrl = (sideBarUrl = null) => {
        const url = new URL(sideBarUrl, window.location.origin);
        return currentUrl == url.pathname ? "bg-secondary" : "";
    };
    return (
        <aside
            id="sidebar-multi-level-sidebar"
            className="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0"
            aria-label="Sidebar"
        >
            <div className="h-full px-3 py-4 overflow-y-auto bg-white shadow-lg">
                <ApplicationLogo className="header-logo mb-3" />
                <ul className="space-y-2 font-medium">
                    <li>
                        <a
                            href={route("dashboard")}
                            className={`flex items-center ${isActiveUrl(
                                route("dashboard")
                            )} p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                className="w-5 h-5 transition duration-75"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 22 21"
                            >
                                <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z" />
                                <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z" />
                            </svg>
                            <span className="ms-3 text-sm text-gray-700">
                                Dashboard
                            </span>
                        </a>
                    </li>

                    <li>
                        <Link
                            href={route("backend.ai_policy_tracker.index")}
                            className={`${isActiveUrl(
                                route("backend.ai_policy_tracker.index")
                            )} flex items-center p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12.75 3.03v.568c0 .334.148.65.405.864l1.068.89c.442.369.535 1.01.216 1.49l-.51.766a2.25 2.25 0 0 1-1.161.886l-.143.048a1.107 1.107 0 0 0-.57 1.664c.369.555.169 1.307-.427 1.605L9 13.125l.423 1.059a.956.956 0 0 1-1.652.928l-.679-.906a1.125 1.125 0 0 0-1.906.172L4.5 15.75l-.612.153M12.75 3.031a9 9 0 0 0-8.862 12.872M12.75 3.031a9 9 0 0 1 6.69 14.036m0 0-.177-.529A2.25 2.25 0 0 0 17.128 15H16.5l-.324-.324a1.453 1.453 0 0 0-2.328.377l-.036.073a1.586 1.586 0 0 1-.982.816l-.99.282c-.55.157-.894.702-.8 1.267l.073.438c.08.474.49.821.97.821.846 0 1.598.542 1.865 1.345l.215.643m5.276-3.67a9.012 9.012 0 0 1-5.276 3.67m0 0a9 9 0 0 1-10.275-4.835M15.75 9c0 .896-.393 1.7-1.016 2.25"
                                />
                            </svg>

                            <span className="ms-3 text-sm text-gray-700 capitalize">
                                AI Plolicy Tracker
                            </span>
                        </Link>
                    </li>

                    <li>
                        <Link
                            href={route("backend.news.index")}
                            className={`${isActiveUrl(
                                route("backend.news.index")
                            )} flex items-center p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"
                                />
                            </svg>

                            <span className="ms-3 text-sm text-gray-700 capitalize">
                                News
                            </span>
                        </Link>
                    </li>

                    {/* cms */}
                    <li>
                        <Link
                            href={route("backend.header_menu.index")}
                            className={`${isActiveUrl(
                                route("backend.header_menu.index")
                            )} flex items-center p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"
                                />
                            </svg>

                            <span className="ms-3 text-sm text-gray-700 capitalize">
                                Cms
                            </span>
                        </Link>
                    </li>
                    {/* users */}
                    <li>
                        <Link
                            href={route("backend.users.index")}
                            className={`${isActiveUrl(
                                route("backend.users.index")
                            )} flex items-center p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                />
                            </svg>

                            <span className="ms-3 text-sm text-gray-700 capitalize">
                                Users
                            </span>
                        </Link>
                    </li>

                    {/* country */}
                    <li>
                        <Link
                            href={route("backend.country.index")}
                            className={`${isActiveUrl(
                                route("backend.country.index")
                            )} flex items-center p-2 rounded-lg hover:bg-blue-100 group`}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                strokeWidth={1.5}
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418"
                                />
                            </svg>
                            <span className="ms-3 text-sm text-gray-700 capitalize">
                                Country
                            </span>
                        </Link>
                    </li>
                    {/*
                    <li>
                        <button
                            type="button"
                            className={`flex items-center w-full p-2 transition duration-75 rounded-lg group hover:bg-blue-100 ${
                                openNavBarDropDown ? "bg-secondary" : ""
                            }`}
                            onClick={handleNavBarDropDown}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"
                                />
                            </svg>

                            <span className="flex-1 ms-3 text-sm text-gray-700 text-left rtl:text-right whitespace-nowrap">
                                CMS
                            </span>
                            <svg
                                className="w-3 h-3"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 10 6"
                            >
                                <path
                                    stroke="#4E87D3"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="m1 1 4 4 4-4"
                                />
                            </svg>
                        </button>

                        {openNavBarDropDown && (
                            <ul
                                id="dropdown-example"
                                className=" py-2 space-y-2"
                            >
                                <li>
                                    <Link
                                        href={route(
                                            "backend.header_menu.index"
                                        )}
                                        className="flex text-sm text-gray-700 items-center w-full p-2 transition duration-75 rounded-lg pl-11 group hover:bg-blue-100"
                                    >
                                        Header Menu
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="#"
                                        className="flex text-sm text-gray-700 items-center w-full p-2 transition duration-75 rounded-lg pl-11 group hover:bg-blue-100"
                                    >
                                        Ai Policy Tracker Project
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href="#"
                                        className="flex text-sm text-gray-700 items-center w-full p-2 transition duration-75 rounded-lg pl-11 group hover:bg-blue-100"
                                    >
                                        Contributing Organizations
                                    </Link>
                                </li>

                                <li>
                                    <Link
                                        href="#"
                                        className="flex text-sm text-gray-700 items-center w-full p-2 transition duration-75 rounded-lg pl-11 group hover:bg-blue-100"
                                    >
                                        Footer
                                    </Link>
                                </li>
                            </ul>
                        )}
                    </li> */}

                    {/* <li>
                        <button
                            type="button"
                            className={`flex items-center w-full p-2 transition duration-75 rounded-lg group hover:bg-blue-100 ${
                                openNavBarDropDown ? "bg-secondary" : ""
                            }`}
                            onClick={handleNavBarDropDown}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="#4E87D3"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                className="size-6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                />
                            </svg>

                            <span className="flex-1 ms-3 text-sm text-gray-700 text-left rtl:text-right whitespace-nowrap">
                                Setting
                            </span>
                            <svg
                                className="w-3 h-3"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 10 6"
                            >
                                <path
                                    stroke="#4E87D3"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="m1 1 4 4 4-4"
                                />
                            </svg>
                        </button>

                        {openNavBarDropDown && (
                            <ul
                                id="dropdown-example"
                                className=" py-2 space-y-2"
                            >
                                <li>
                                    <Link
                                        href="#"
                                        className="flex text-sm text-gray-700 items-center w-full p-2 transition duration-75 rounded-lg pl-11 group hover:bg-blue-100"
                                    >
                                        Roles
                                    </Link>
                                </li>
                            </ul>
                        )}
                    </li> */}
                </ul>
            </div>
        </aside>
    );
}
