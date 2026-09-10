import Layout from "@/Layouts/Backend/Layout";
import { Head } from "@inertiajs/react";
import React from "react";
import GraphChart from "./Services/GraphChart";

export default function Dashboard({ data, graphData }) {
    return (
        <Layout>
            <Head title="Dashboard" />
            <div className="p-4 rounded-lg">
                <div className="grid grid-cols-3 gap-4 mb-4">
                    <div className="flex items-center justify-evenly h-24 rounded bg-white">
                        <div className="bg-blue-950 h-8 w-8 rounded-lg flex justify-center items-center">
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
                        </div>
                        <div>
                            <p className="text-2xl font-bold">
                                {data?.aiPolicyCount ?? 0}
                            </p>
                            <p className="text-gray-400">Total (AI) Policy</p>
                        </div>
                    </div>
                    <div className="flex items-center justify-evenly h-24 rounded bg-white">
                        <div className="bg-blue-950  h-8 w-8 rounded-lg flex justify-center items-center">
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
                        </div>
                        <div>
                            <p className="text-2xl font-bold">
                                {data?.newsCount ?? 0}
                            </p>
                            <p className="text-gray-400">Totat News</p>
                        </div>
                    </div>
                    <div className="flex items-center justify-evenly h-24 rounded bg-white">
                        <div className="bg-blue-950  h-8 w-8 rounded-lg flex justify-center items-center">
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
                        </div>
                        <div>
                            <p className="text-2xl font-bold">
                                {data?.usersCount ?? 0}
                            </p>
                            <p className="text-gray-400">Total Users</p>
                        </div>
                    </div>
                </div>
                <div className="mb-4 rounded bg-white px-5 py-5">
                    <p className="font-bold">
                        AI Policy Tracker Graph
                        <span className="text-gray-500 font-semibold ml-5">
                            ({data.currentYear ?? ""} -{data.upcomingYear ?? ""}
                            )
                        </span>
                    </p>
                    <GraphChart graphData={graphData} />
                </div>
                <div className="grid grid-cols-2 gap-4 mb-4">
                    <div className="rounded bg-white min-h-28 py-5">
                        <div className="flex items-center justify-between px-5 mb-3">
                            <div>
                                <span className="text-gray-600 font-bold text-xs">
                                    Activities
                                </span>
                            </div>
                            <div>
                                <span className="text-gray-600 font-bold text-xs border-b-2 border-blue-900">
                                    Count
                                </span>
                            </div>
                        </div>
                        <hr />
                        <div className="flex items-center justify-between px-5 mt-2 mb-2">
                            <div>
                                <span className="text-gray-500 font-semibold text-xs">
                                    Verified users
                                </span>
                            </div>
                            <div className="font-bold text-green-500">
                                {data?.verifiedUsersCount ?? 0}
                            </div>
                        </div>
                        <div className="flex items-center justify-between px-5 mb-2">
                            <div>
                                <span className="text-gray-500 font-semibold text-xs">
                                    Unverified users
                                </span>
                            </div>
                            <div className="font-bold text-red-500">
                                {data?.notVerifiedUsersCount ?? 0}
                            </div>
                        </div>
                        <hr />
                        <div className="flex items-center justify-between px-5 mt-2 mb-2">
                            <div>
                                <span className="text-gray-500 font-semibold text-xs">
                                    Total Bookmarks
                                </span>
                            </div>
                            <div className="font-bold text-blue-300">
                                {data?.bookMarkCount ?? 0}
                            </div>
                        </div>
                    </div>
                </div>
                {/* <div className="flex items-center justify-center h-48 mb-4 rounded bg-gray-50 dark:bg-gray-800">
                    <p className="text-2xl text-gray-400 dark:text-gray-500">
                        <svg
                            className="w-3.5 h-3.5"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 18 18"
                        >
                            <path
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M9 1v16M1 9h16"
                            />
                        </svg>
                    </p>
                </div> */}
                {/* <div className="grid grid-cols-2 gap-4">
                    <div className="flex items-center justify-center rounded bg-gray-50 h-28 dark:bg-gray-800">
                        <p className="text-2xl text-gray-400 dark:text-gray-500">
                            <svg
                                className="w-3.5 h-3.5"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 18 18"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M9 1v16M1 9h16"
                                />
                            </svg>
                        </p>
                    </div>
                    <div className="flex items-center justify-center rounded bg-gray-50 h-28 dark:bg-gray-800">
                        <p className="text-2xl text-gray-400 dark:text-gray-500">
                            <svg
                                className="w-3.5 h-3.5"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 18 18"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M9 1v16M1 9h16"
                                />
                            </svg>
                        </p>
                    </div>
                    <div className="flex items-center justify-center rounded bg-gray-50 h-28 dark:bg-gray-800">
                        <p className="text-2xl text-gray-400 dark:text-gray-500">
                            <svg
                                className="w-3.5 h-3.5"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 18 18"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M9 1v16M1 9h16"
                                />
                            </svg>
                        </p>
                    </div>
                    <div className="flex items-center justify-center rounded bg-gray-50 h-28 dark:bg-gray-800">
                        <p className="text-2xl text-gray-400 dark:text-gray-500">
                            <svg
                                className="w-3.5 h-3.5"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 18 18"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M9 1v16M1 9h16"
                                />
                            </svg>
                        </p>
                    </div>
                </div> */}
            </div>
        </Layout>
    );
}
