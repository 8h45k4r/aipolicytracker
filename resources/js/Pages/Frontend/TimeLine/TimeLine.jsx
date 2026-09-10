import { AppLayout } from "@/Layouts/AppLayout";
import { Head } from "@inertiajs/react";
import React from "react";
export default function TimeLine({ timeLines, lastUpdateDate }) {
    return (
        <AppLayout>
            <Head title="Timeline" />
            <div className="block md:relative top-[-60px] w-full ">
                <div className="lg:border rounded-md w-full md:bg-white py-5">
                    <div className=" md:border-b-2 pb-4 lg:px-5 flex justify-between items-center">
                        <div>
                            <div>
                                <p className="font-bold text-primary-light text-lg">
                                    Timeline
                                </p>
                            </div>
                            <div className="flex gap-5 mt-3 text-sm">
                                <p className=" text-sm">
                                    Last update: <span>{lastUpdateDate}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white py-5 text-wrap md:py-0 rounded-md md:rounded-none">
                        <div className="table-auto title_line_action mb-2 w-full">
                            <div className="hidden md:block">
                                <div className="flex">
                                    <div
                                        className="timeline-action status-action flex items-center gap-1"
                                        style={{ width: "25%" }}
                                    >
                                        <span>
                                            <svg
                                                viewBox="0 0 16 16"
                                                width="1em"
                                                height="1em"
                                                focusable="false"
                                                role="img"
                                                aria-label="arrow repeat"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="currentColor"
                                                className="bi-arrow-repeat b-icon bi"
                                            >
                                                <g>
                                                    <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"></path>
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"
                                                    ></path>
                                                </g>
                                            </svg>
                                        </span>
                                        Status update
                                    </div>
                                    <div
                                        className="timeline-action add-action flex items-center gap-1"
                                        style={{ width: "25%" }}
                                    >
                                        <span>
                                            <svg
                                                viewBox="0 0 16 16"
                                                width="1em"
                                                height="1em"
                                                focusable="false"
                                                role="img"
                                                aria-label="plus circle"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="currentColor"
                                                className="bi-plus-circle b-icon bi"
                                            >
                                                <g>
                                                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
                                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"></path>
                                                </g>
                                            </svg>
                                        </span>
                                        Added data
                                    </div>
                                    <div
                                        className="timeline-action update-action flex items-center gap-1"
                                        style={{ width: "25%" }}
                                    >
                                        <svg
                                            viewBox="0 0 16 16"
                                            width="1em"
                                            height="1em"
                                            focusable="false"
                                            role="img"
                                            aria-label="arrow repeat"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="currentColor"
                                            className="bi-arrow-repeat b-icon bi"
                                        >
                                            <g>
                                                <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"></path>
                                                <path
                                                    fillRule="evenodd"
                                                    d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"
                                                ></path>
                                            </g>
                                        </svg>
                                        Updated data
                                    </div>
                                    <div
                                        className="timeline-action delete-action flex items-center gap-1"
                                        style={{ width: "25%" }}
                                    >
                                        <span>
                                            <svg
                                                viewBox="0 0 16 16"
                                                width="1em"
                                                height="1em"
                                                focusable="false"
                                                role="img"
                                                aria-label="dash circle"
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="currentColor"
                                                className="bi-dash-circle b-icon bi"
                                            >
                                                <g>
                                                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
                                                    <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z"></path>
                                                </g>
                                            </svg>
                                        </span>
                                        Deleted data
                                    </div>
                                </div>
                            </div>
                            <div>
                                {Object.entries(timeLines).map(
                                    ([year, items]) => (
                                        <div key={year}>
                                            <h3 className="flex items-center mb-1 text-xs mt-2 font-bold text-primary capitalize ml-6">
                                                {year}
                                            </h3>

                                            {items.map((item) => {
                                                // Create arrays to hold activity logs by type
                                                const statusLogs = [];
                                                const addedLogs = [];
                                                const updatedLogs = [];
                                                const deletedLogs = [];

                                                // Categorize logs based on activity_name
                                                item.a_i_policy_activity_logs.forEach(
                                                    (log) => {
                                                        switch (
                                                            log.activity_name
                                                        ) {
                                                            case "status update":
                                                                statusLogs.push(
                                                                    log
                                                                );
                                                                break;
                                                            case "added data":
                                                                addedLogs.push(
                                                                    log
                                                                );
                                                                break;
                                                            case "updated data":
                                                                updatedLogs.push(
                                                                    log
                                                                );
                                                                break;
                                                            case "delete data":
                                                                deletedLogs.push(
                                                                    log
                                                                );
                                                                break;
                                                            default:
                                                                break;
                                                        }
                                                    }
                                                );

                                                return (
                                                    <div
                                                        className="mb-10 relative"
                                                        key={item.id}
                                                    >
                                                        {/* Check if item.a_i_policy_activity_logs exists and is an array */}
                                                        <div>
                                                            <div className="flex flex-wrap">
                                                                <div className="flex flex-wrap md:basis-1/4">
                                                                    {statusLogs.length >
                                                                    0 ? (
                                                                        statusLogs.map(
                                                                            (
                                                                                log,
                                                                                index
                                                                            ) => (
                                                                                <div
                                                                                    className={`w-full p-2 ${
                                                                                        index ===
                                                                                        0
                                                                                            ? "mt-0"
                                                                                            : "mt-2"
                                                                                    }`}
                                                                                    key={
                                                                                        log.id
                                                                                    }
                                                                                >
                                                                                    <div className="bg-white border border-gray-200 rounded-lg shadow">
                                                                                        <h5 className="border-b-2 border-neutral-100 px-6 py-2 text-xs font-bold leading-tight dark:border-white/10 status-action text-dark capitalize">
                                                                                            {item.ai_policy_name ||
                                                                                                `Item ${item.id}`}
                                                                                        </h5>
                                                                                        <div className="px-6 status-action-item">
                                                                                            <div
                                                                                                className="text-xs py-2"
                                                                                                dangerouslySetInnerHTML={{
                                                                                                    __html: log.description,
                                                                                                }}
                                                                                            />
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            )
                                                                        )
                                                                    ) : (
                                                                        <div className="flex basis-1/4 p-2">
                                                                            <div className="w-full text-center text-gray-500">
                                                                                {/* Optional placeholder for empty state */}
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                <div className="block md:flex flex-wrap md:basis-1/4">
                                                                    {/* Added Logs */}
                                                                    {addedLogs.length >
                                                                    0 ? (
                                                                        addedLogs.map(
                                                                            (
                                                                                log,
                                                                                index
                                                                            ) => (
                                                                                <div
                                                                                    className={`w-full p-2 ${
                                                                                        index ===
                                                                                        0
                                                                                            ? "mt-0"
                                                                                            : "mt-2"
                                                                                    }`}
                                                                                    key={
                                                                                        log.id
                                                                                    }
                                                                                >
                                                                                    <div className="bg-white border border-gray-200 rounded-lg shadow">
                                                                                        <h5 className="border-b-2 flex gap-2 border-neutral-100 px-6 py-2 text-xs font-bold leading-tight add-action text-dark capitalize">
                                                                                            {item.ai_policy_name ||
                                                                                                `Item ${item.id}`}
                                                                                            <span className="font-light block md:hidden">
                                                                                                <svg
                                                                                                    viewBox="0 0 16 16"
                                                                                                    width="1em"
                                                                                                    height="1em"
                                                                                                    focusable="false"
                                                                                                    role="img"
                                                                                                    aria-label="plus circle"
                                                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                                                    fill="currentColor"
                                                                                                    className="bi-plus-circle b-icon bi"
                                                                                                >
                                                                                                    <g>
                                                                                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
                                                                                                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"></path>
                                                                                                    </g>
                                                                                                </svg>
                                                                                            </span>
                                                                                            <span className="block md:hidden font-light">
                                                                                                Added
                                                                                                data
                                                                                            </span>
                                                                                        </h5>
                                                                                        <div className="px-6 add-action-item">
                                                                                            <div
                                                                                                className="text-xs py-2"
                                                                                                dangerouslySetInnerHTML={{
                                                                                                    __html: log.description,
                                                                                                }}
                                                                                            />
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            )
                                                                        )
                                                                    ) : (
                                                                        <div className="flex basis-1/4 p-2">
                                                                            <div className="w-full text-center text-gray-500">
                                                                                {/* Optional placeholder for empty state */}
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                <div className="flex flex-wrap md:basis-1/4">
                                                                    {updatedLogs.length >
                                                                    0 ? (
                                                                        updatedLogs.map(
                                                                            (
                                                                                log,
                                                                                index
                                                                            ) => (
                                                                                <div
                                                                                    className={` ${
                                                                                        index ===
                                                                                        0
                                                                                            ? "w-full"
                                                                                            : "w-full mt-2"
                                                                                    } p-2`}
                                                                                    key={
                                                                                        log.id
                                                                                    }
                                                                                >
                                                                                    <div className="bg-white border border-gray-200 rounded-lg shadow ">
                                                                                        <h5 className="border-b-2 flex gap-2 border-neutral-100 px-6 py-2 text-xs font-bold leading-tight dark:border-white/10 update-action text-dark capitalize">
                                                                                            {item.ai_policy_name ||
                                                                                                `Item ${item.id}`}
                                                                                            <svg
                                                                                                viewBox="0 0 16 16"
                                                                                                width="1em"
                                                                                                height="1em"
                                                                                                focusable="false"
                                                                                                role="img"
                                                                                                aria-label="arrow repeat"
                                                                                                xmlns="http://www.w3.org/2000/svg"
                                                                                                fill="currentColor"
                                                                                                className="bi-arrow-repeat b-icon bi block md:hidden"
                                                                                            >
                                                                                                <g>
                                                                                                    <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"></path>
                                                                                                    <path
                                                                                                        fillRule="evenodd"
                                                                                                        d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"
                                                                                                    ></path>
                                                                                                </g>
                                                                                            </svg>
                                                                                            <span className="font-light block md:hidden">
                                                                                                Updated
                                                                                                data
                                                                                            </span>
                                                                                        </h5>
                                                                                        <div className="px-6 update-action-item">
                                                                                            <div
                                                                                                className="text-xs py-2"
                                                                                                dangerouslySetInnerHTML={{
                                                                                                    __html: log.description,
                                                                                                }}
                                                                                            />
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            )
                                                                        )
                                                                    ) : (
                                                                        <div className="flex basis-1/4 p-2">
                                                                            <div className="w-full">
                                                                                {/* Optional placeholder for empty state */}
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                <div className="flex flex-wrap md:basis-1/4">
                                                                    {deletedLogs.length >
                                                                    0 ? (
                                                                        deletedLogs.map(
                                                                            (
                                                                                log,
                                                                                index
                                                                            ) => (
                                                                                <div
                                                                                    className={`w-full p-2 ${
                                                                                        index ===
                                                                                        0
                                                                                            ? "mt-0"
                                                                                            : "mt-2"
                                                                                    }`}
                                                                                    key={
                                                                                        log.id
                                                                                    }
                                                                                >
                                                                                    <div className="bg-white border border-gray-200 rounded-lg shadow">
                                                                                        <h5 className="border-b-2 border-neutral-100 px-6 py-2 text-xs font-medium leading-tight dark:border-white/10 delete-action text-dark capitalize">
                                                                                            {item.ai_policy_name ||
                                                                                                `Item ${item.id}`}
                                                                                            <span className=" block md:hidden">
                                                                                                <svg
                                                                                                    viewBox="0 0 16 16"
                                                                                                    width="1em"
                                                                                                    height="1em"
                                                                                                    focusable="false"
                                                                                                    role="img"
                                                                                                    aria-label="dash circle"
                                                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                                                    fill="currentColor"
                                                                                                    className="bi-dash-circle b-icon bi"
                                                                                                >
                                                                                                    <g>
                                                                                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"></path>
                                                                                                        <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z"></path>
                                                                                                    </g>
                                                                                                </svg>
                                                                                            </span>
                                                                                            <span className="font-light block md:hidden">
                                                                                                Deleted
                                                                                                data
                                                                                            </span>
                                                                                        </h5>
                                                                                        <div className="px-6 delete-action-item">
                                                                                            <div
                                                                                                className="text-xs py-2"
                                                                                                dangerouslySetInnerHTML={{
                                                                                                    __html: log.description,
                                                                                                }}
                                                                                            />
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            )
                                                                        )
                                                                    ) : (
                                                                        <div className="flex basis-1/4 p-2">
                                                                            <div className="w-full text-center text-gray-500">
                                                                                {/* Optional placeholder for empty state */}
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                            <hr />
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
