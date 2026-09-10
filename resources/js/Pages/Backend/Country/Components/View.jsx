import React, { useEffect, useState } from "react";
import Button from "@/Components/Button";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { ToggleSwitch } from "flowbite-react";

import Input from "@/Components/Input";
import ViewIcon from "@/Components/ViewIcon";

export default function CountryView({ country }) {
    // state for show country related ai policy
    const [isAiPolicyNameView, setIsAiPolicyNameView] = useState(false);
    return (
        <div>
            <Head title={country.name} />
            <div>
                <div className="w-full">
                    <Input
                        value={country.name}
                        htmlFor="name"
                        label="Country Name"
                        type="text"
                        disabled
                    />
                </div>

                <div className="w-full mt-2">
                    <Input
                        value={country.symbol}
                        htmlFor="name"
                        label="Symbol"
                        type="text"
                        disabled
                    />
                </div>

                <div className="flex flex-row justify-between items-start gap-4 mb-4 mt-4">
                    <ToggleSwitch
                        checked={country.status}
                        label="country Status"
                    />
                    <span
                        className={`bg-green-500 text-white px-2 py-1 rounded-full ${
                            country.status == 1 ? "bg-green-500" : "bg-red-500"
                        }`}
                        style={{ fontSize: "10px" }}
                    >
                        {country.status == 1 ? "Active" : "Inactive"}
                    </span>
                </div>
                <hr />
                <div className="mt-3 mb-3 flex justify-between">
                    <div className="flex gap-2 items-center">
                        <strong className="text-gray-600">
                            Total AI policy
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
                        {country.ai_policy_trackers_count ?? 0}
                    </p>
                </div>

                {isAiPolicyNameView && (
                    <>
                        <hr />
                        <div className="mt-3">
                            <strong className="text-gray-500">
                                Ai Policy Name
                            </strong>

                            <ul className="text-gray-500 mt-3">
                                {country?.ai_policy_trackers.map(
                                    (list, index) => (
                                        <li key={list.id}>
                                            {index + 1}. {list.ai_policy_name}
                                        </li>
                                    )
                                )}

                                {!country?.ai_policy_trackers.length > 0 && (
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
