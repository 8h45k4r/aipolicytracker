import SelectInputLists from "@/Components/Map/SelectInputList";
import SelectInput from "@/Components/SelectInput";
import Status from "@/Components/Status/Status";
import { AppLayout } from "@/Layouts/AppLayout";
import { Head } from "@inertiajs/react";
import React, { useState } from "react";
import NewsCard from "./Components/NewsCard";
import NoImage from "@/assets/images/no-image/no-image.png";


export default function News({ news }) {
    const [visibleDiv, setVisibleDiv] = useState(false);

    const filteredLabels = ["AI Policy Name", "Country / Region"];

    return (
        <AppLayout>
            <Head title="News" />
            <div className="content-wrapper mt-5 xl:mt-0 lg:mt-0 md:relative md:top-[-60px] md:flex justify-center">
                <div className="rounded-md md:w-3/4 md:bg-white py-5">
                    <div className="" style={{ minHeight: "500px" }}>
                        <div className="text-gray-900 font-bold text-xl mb-2 px-5">
                            <div className="md:flex justify-between align-items-center">
                                {news.title}
                                <p className="text-xs mt-3 md:mt-0 md:text-sm text-gray-600 md:flex items-center gap-2 justify-center">
                                    <i className="fa-regular fa-clock"> </i>
                                    {news.upload_date ?? "-"}
                                </p>
                            </div>
                        </div>
                        <div className=" bg-red-50 w-full h-[400px] overflow-hidden">
                            {news.thumbnail?.path ? (
                                <img
                                    src={`/storage/${news.thumbnail?.path}`}
                                    alt="no-image"
                                    className="w-full h-full object-fill"
                                />
                            ) : (
                                <div className="w-full h-full flex justify-center items-center">
                                    <img src={NoImage} alt=""  className="h-[100px] w-[80px]" />
                                </div>
                            )}
                        </div>

                        <div className="px-5 mt-5">
                            <p className="text-gray-500 text-xl" dangerouslySetInnerHTML={{ __html: news.description }}></p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
