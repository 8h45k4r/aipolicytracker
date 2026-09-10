import { AppLayout } from "@/Layouts/AppLayout";
import { Head, Link } from "@inertiajs/react";
import React from "react";
import AiNews from "./Components/AiNews";
import SingleAiInfo from "./Components/SingleAiInfo";
import AiTimeLine from "./Components/AiTimeLine";

export default function SingleAiPolicyTracker({
    aiPolicyTrackerWithRelatedNews,
    latestDateOfUpdateAiPolicyTracker = null,
}) {
    return (
        <AppLayout>
            <Head title="Single Ai Policy Tracker" />
            <div className="flex gap-5 mt-5 md:mt-0 text-sm md:hidden mb-5">
                <Link href="/" className=" text-sm flex gap-2 hover:underline">
                    <span>
                        <i className="fa-regular fa-circle-left"></i>
                    </span>
                    <span>Go to back</span>
                </Link>
            </div>
            <div className="md:flex flex-row gap-x-4 content-wrapper xl:mt-0 lg:mt-0 md:relative md:top-[-60px]">
                <div className="w-full md:w-1/4">
                    <SingleAiInfo
                        aiPolicyTrackerdetail={aiPolicyTrackerWithRelatedNews}
                    />
                </div>
                <div className="w-full mt-5 md:mt-0 md:w-2/4">
                    <AiNews newsLists={aiPolicyTrackerWithRelatedNews.news} />
                </div>
                <div className="w-full mt-5 md:mt-0 md:w-1/4">
                    <AiTimeLine
                        timeLines={
                            aiPolicyTrackerWithRelatedNews.a_i_policy_activity_logs
                        }
                        latestDateOfUpdateAiPolicyTracker={
                            latestDateOfUpdateAiPolicyTracker
                        }
                    />
                </div>
            </div>
            {/* <div className="content-wrapper mt-5"> */}
            {/* <div className="flex gap-5 mt-3 text-sm mb-5">
                        <Link
                            href="/"
                            className=" text-sm flex gap-2 hover:underline"
                        >
                            <span>
                                <i className="fa-regular fa-circle-left"></i>
                            </span>
                            <span>Go to back</span>
                        </Link>
                    </div> */}
            {/* Flexbox on large screens (lg and xl) */}
            {/* <div className="w-full block lg:flex gap-8"> */}
            {/* Single AI Info */}
            {/* <div className="w-full lg:max-w-[25%]">
                            <SingleAiInfo
                                aiPolicyTrackerdetail={
                                    aiPolicyTrackerWithRelatedNews
                                }
                            />
                        </div> */}

            {/* Ai News */}
            {/* <div className="w-full mt-5 lg:mt-0">
                            <AiNews
                                newsLists={aiPolicyTrackerWithRelatedNews.news}
                            />
                        </div> */}

            {/* Ai Timeline */}
            {/* <div className="w-full mt-5 lg:mt-0 lg:max-w-[25%]">
                            <AiTimeLine
                                timeLines={
                                    aiPolicyTrackerWithRelatedNews.a_i_policy_activity_logs
                                }
                                latestDateOfUpdateAiPolicyTracker={
                                    latestDateOfUpdateAiPolicyTracker
                                }
                            />
                        </div>
                    </div>
                </div> */}
        </AppLayout>
    );
}
