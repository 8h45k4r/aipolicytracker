import Contact from "@/Components/Contact/Contact";
import Contributor from "@/Components/Contributor/Contributor";
import { Link } from "@inertiajs/react";
import React from "react";

export default function Description({
    descriptionData = [],
    contributorLists = [],
    contactLists = [],
}) {
    return (
        <div className="bg-white h-fit py-5 rounded-md w-full">
            <div className="flex justify-between items-center border-b px-5 pb-4">
                <div>
                    <h1 className=" text-base lg:text-xl font-bold mb-2 text-primary-light">
                        AIPolicyTracker Project
                    </h1>
                    <p>
                    AIPolicyTracker is a comprehensive resource that
                        monitors and analyzes artificial intelligence (AI)
                        policies and regulations across various jurisdictions.
                    </p>
                </div>
            </div>

            <div className="mt-3 px-5">
                <div className="block md:flex sm:flex">
                    <div className="w-full md:w-1/2 sm:w-1/2">
                        <strong className="text-primary-light">
                            Description
                        </strong>

                        {descriptionData.map((data, index) => (
                            <div
                            className="block sm:flex gap-2 mt-3 text-primary-light"
                            key={index}
                        >
                            <p>
                                <strong className=" capitalize">
                                    {data.name}
                                </strong>
                            </p>
                            <p className="text-gray-500">{data.title}</p>
                        </div>
                        ))}
                    </div>

                    <div className="lg:w-1/2 mt-5 lg:mt-0">
                        {/* key contributors */}
                        <div className="block md:flex justify-between">
                            <Contributor contributorLists={contributorLists} />
                            <Contact contactLists={contactLists} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
