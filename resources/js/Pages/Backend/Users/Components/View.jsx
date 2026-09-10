import React, { useEffect, useState } from "react";
import AddIcon from "@/Components/AddIcon";
import Button from "@/Components/Button";

import Layout from "@/Layouts/Backend/Layout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import DeleteIcon from "@/Components/DeleteIcon";
import ViewIcon from "@/Components/ViewIcon";

// Helper function to generate avatar URL
const generateAvatarUrl = (name) => {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(
        name
    )}&background=EBF4FF&color=7F9CF5`;
};

export default function View({ user, name }) {
    const avatarUrl = generateAvatarUrl(user.name);

    const [isAiPolicyNameView, setIsAiPolicyNameView] = useState(false);
    return (
        <div className="w-full">
            <div className="rounded-lg bg-white w-full">
                <div className="border-b pb-3  px-5 flex items-center gap-2">
                    <div className="flex justify-center w-[43px] h-[43px] rounded-full overflow-hidden">
                        <img
                            src={avatarUrl}
                            alt="User Avatar"
                            className="h-full w-full"
                        />
                    </div>
                    <div>
                        <p className="text-gray-500">{user.name}</p>
                        {/* <span className=" bg-green-500 text-white px-1 py-1 rounded-full text-xs">
                        {user.email_verified_at ? "Verified":"Unverified"}
                        </span> */}
                        <span
                            className={`bg-green-500 text-white px-2 py-1 rounded-full ${
                                user.email_verified_at
                                    ? "bg-green-500"
                                    : "bg-red-500"
                            }`}
                            style={{ fontSize: "10px" }}
                        >
                            {user.email_verified_at ? "Verified" : "Unverified"}
                        </span>
                    </div>
                </div>

                {/* user info */}

                <div className="px-5 mt-3 mb-3 flex justify-between">
                    <strong className="text-gray-600">Status</strong>
                    <span
                        className={`bg-green-500 text-white px-5 py-1 rounded-full text-xs ${
                            user.user_info?.status == 1
                                ? "bg-green-500"
                                : "bg-red-500"
                        }`}
                    >
                        {user.user_info?.status == 1 ? "Active" : "Inactive"}
                    </span>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3">
                    <strong className="text-gray-600">Email</strong>
                    <p className="text-gray-500">{user.email}</p>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3 flex justify-between">
                    <strong className="text-gray-600">Phone No.</strong>
                    <span className="text-gray-500">
                        {user.user_info?.phone_no}
                    </span>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3">
                    <strong className="text-gray-600">Organization Name</strong>
                    <p className="text-gray-500">
                        {user.user_info?.organization_name ?? "N/A"}
                    </p>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3">
                    <strong className="text-gray-600">
                        Organization Email
                    </strong>
                    <p className="text-gray-500">
                        {user.user_info?.organization_email ?? "N/A"}
                    </p>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3 flex justify-between">
                    <strong className="text-gray-600">IP Address.</strong>
                    <span className="text-gray-500">192.168.1.74</span>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3  flex-col justify-between">
                    <strong className="text-gray-600">User Browser</strong>
                    <p className="text-gray-500">
                        {user.user_info?.user_agent}
                    </p>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3 flex justify-between">
                    <strong className="text-gray-600">
                        Terms and condition
                    </strong>
                    <span className="text-gray-500">
                        {user.user_info?.terms_condition == 1
                            ? "Accepted"
                            : "Rejected"}
                    </span>
                </div>
                <hr />

                <div className="px-5 mt-3 mb-3 flex justify-between">
                    <strong className="text-gray-600">Created at</strong>
                    <span className="text-gray-500">
                        {user.formatted_created_at}
                    </span>
                </div>

                <hr />

                <div className="px-5 mt-3 flex justify-between">
                    <div className="flex gap-2 items-center">
                        <strong className="text-gray-600">
                            Total Bookmarks
                        </strong>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                            className="size-5 cursor-pointer hover:text-blue-500"
                            onClick={() =>
                                setIsAiPolicyNameView(!isAiPolicyNameView)
                            }
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"
                            />
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                            />
                        </svg>
                    </div>
                    <p className="text-gray-500">
                        {user.book_marks_count ?? 0}
                    </p>
                </div>

                {isAiPolicyNameView && (
                    <>
                        <hr />
                        <div className="mt-3 px-5">
                            <strong className="text-gray-500">
                                Ai Policy Name
                            </strong>

                            <ul className="text-gray-500 mt-3">
                                {user?.book_marks.map((list, index) => (
                                    <li key={list.id}>
                                        {index + 1}.{" "}
                                        {list.aipolicy?.ai_policy_name}
                                    </li>
                                ))}

                                {!user?.book_marks.length > 0 && (
                                    <li>No any ai policy</li>
                                )}
                            </ul>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
