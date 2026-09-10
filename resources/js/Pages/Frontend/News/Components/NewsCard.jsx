import NoTableData from "@/Components/Table/NoTableData";
import { Link } from "@inertiajs/react";
import React from "react";
import NoImage from "@/assets/images/no-image/no-image.png";
import DOMPurify from "dompurify";
import Responsive from "@/Components/Responsive/Responsive";

export default function NewsCard({ newsLists = [] }) {
    const limitWords = (html, limit) => {
        const text = DOMPurify.sanitize(html).replace(/<[^>]+>/g, " "); // Remove HTML tags
        const words = text.split(" ");
        return (
            words.slice(0, limit).join(" ") +
            (words.length > limit ? "..." : "")
        );
    };

    return (
        <div className="">
            {newsLists.map((newsList, index) => (
                <div
                    className="px-[5%] py-[5%] rounded md:px-[10%] lg:px-5 w-full lg:max-w-full
                 lg:flex border-b border-light-border md:pb-5 bg-white"
                    key={index}
                >
                    {newsList.thumbnail?.path ? (
                        <div className="lg:w-[40%]">
                            <div className="h-[136px] ">
                                <img
                                    className="w-full h-full"
                                    src={`/storage/${newsList.thumbnail?.path}`}
                                    alt=""
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="lg:w-[25%]">
                            <div className="h-48 ">
                                <div className="flex justify-center">
                                    <img
                                        src={NoImage}
                                        alt=""
                                        className="max-h-[200px] max-w-[90px] object-cover"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="lg:w-[75%] px-4 flex flex-col justify-between leading-normal pt-4">
                        <div className="">
                            <p className="text-xs mb-3 text-light-blue flex items-center gap-[4px]">
                                <span>
                                    <svg
                                        viewBox="0 0 16 16"
                                        width="1em"
                                        height="1em"
                                        focusable="false"
                                        role="img"
                                        aria-label="calendar week"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="currentColor"
                                       className="bi-calendar-week b-icon bi"
                                    >
                                        <title>Date</title>
                                        <g>
                                            <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"></path>
                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"></path>
                                        </g>
                                    </svg>
                                </span>
                                {newsList.upload_date}
                            </p>
                            <div className="mb-2 text-xl font-bold tracking-tight text-primary truncate">
                                <Link
                                    href={route("news.single", newsList.id)}
                                    className="hover:underline"
                                >
                                    {newsList.title ?? ""}
                                </Link>
                            </div>
                            {/* <div
                            className="text-gray-700 text-base truncate"
                            dangerouslySetInnerHTML={{
                                __html: newsList.description ?? "",
                            }}
                        ></div> */}

                            <p className="mb-3 font-normal">
                                {limitWords(newsList.description, 50)}
                            </p>
                        </div>
                    </div>
                </div>
            ))}

            {newsLists.length == 0 && (
                <div>
                    <Responsive responsive={["lg", "xl"]}>
                        <NoTableData noTableDataTitle={"no data found"} />
                    </Responsive>
                    <Responsive responsive={["sm", "md"]}>
                        <h1 className="font-bold mt-2 text-primary text-lg text-center">
                            No data found
                        </h1>
                    </Responsive>
                </div>
            )}
        </div>
    );
}
