import TimeLine from "@/Pages/Frontend/TimeLine/TimeLine";
import React from "react";

export default function AiTimeLine({
    timeLines = [],
    latestDateOfUpdateAiPolicyTracker = null,
}) {
    return (
        <div className="bg-white rounded-md py-3">
            <div className=" border-b pb-4 px-5">
                <div>
                    <p className="font-bold text-primary-light text-lg">Time Line</p>
                </div>
                <div className="flex gap-5 mt-3 text-sm">
                    <p className="text-sm text-primary">
                        Last update:
                        <span>{latestDateOfUpdateAiPolicyTracker}</span>
                    </p>
                </div>
            </div>

            <div className="mt-5 px-5 bg-white py-5 text-wrap md:py-0 rounded-md md:rounded-none">
                <ol className="relative border-s-4 border-blue-100">
                    {timeLines.map((timeLine, index) => (
                        <li key={index} className="mb-10 ms-6">
                            <span className="absolute flex items-center justify-center w-6 h-6 bg-blue-100 rounded-full -start-3 ring-8 ring-white">
                                <svg
                                    className="w-2.5 h-2.5 text-blue-800"
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                                </svg>
                            </span>
                            <h3 className="flex items-center mb-1 text-sm font-bold text-primary capitalize">
                                {new Date(timeLine.created_at).toLocaleString(
                                    "default",
                                    { month: "long", year: "numeric" }
                                )}
                            </h3>

                            <div className="mb-2 bg-blue-100 px-5 py-2 rounded-md">
                                <p className=" text-blue-500 flex items-center gap-1 font-bold text-xs">
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
                                    {timeLine.activity_name}
                                </p>
                            </div>
                            <div className="mb-2 bg-blue-100 px-5 py-2 rounded-md">
                                <p
                                    className="text-xs font-normal text-blue-500"
                                    dangerouslySetInnerHTML={{
                                        __html: timeLine.description,
                                    }}
                                ></p>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>
        </div>
    );
}
