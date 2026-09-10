import NewsCard from "@/Pages/Frontend/News/Components/NewsCard";
import React from "react";

export default function AiNews({ newsLists = [] }) {
    return (
        <div className="bg-white rounded-md py-3">
            <div className="border-b pb-4 px-5">
                <div>
                    <p className="font-bold text-primary-light text-lg">News</p>
                </div>
                <div className="flex gap-5 mt-3 text-sm">
                    <p className=" text-sm text-primary">
                        Sorted by date
                    </p>
                </div>
            </div>

            <NewsCard newsLists={newsLists} />
        </div>
    );
}
