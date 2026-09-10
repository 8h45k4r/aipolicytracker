import React, { useRef } from "react";
import { Link } from "@inertiajs/react";
import Slider from "react-slick";
import "slick-carousel/slick/slick.css";
import "slick-carousel/slick/slick-theme.css";
import NoImage from "@/assets/images/no-image/no-image.png";
import DOMPurify from 'dompurify';

import { limitWords } from '@/utils/limitWords';

const News = ({ news }) => {
    const sliderRef = useRef(null);

    const settings = {
        dots: false,
        infinite: true,
        slidesToShow: 4,
        slidesToScroll: 1,
        autoplay: false,
        autoplaySpeed: 4000,
        pauseOnHover: true,
        adaptiveHeight: true,
        arrows: false,
    };

    return (
        <div className="bg-white h-fit pb-15 rounded-md w-full mt-6">
            <div className="flex justify-between items-center border-b border-light-border p-[16px]">
                <div>
                    <h1 className="text-xl font-bold mb-2 text-primary-light">News</h1>
                    <Link href="/news" className="hover:underline text-light-blue">
                        Go to all news
                        <i className="fa-regular fa-circle-right ml-2"></i>
                    </Link>
                </div>
                <div className="flex gap-6">
                    <i
                        className="fa-solid fa-left-long hover:cursor-pointer text-light-blue"
                        onClick={() => sliderRef.current.slickPrev()}
                    ></i>
                    <i
                        className="fa-solid fa-right-long hover:cursor-pointer text-light-blue"
                        onClick={() => sliderRef.current.slickNext()}
                    ></i>
                </div>
            </div>
            <div className="text" style={{ margin: '0 -10px' }}>
                <Slider ref={sliderRef} {...settings} className="flex gap-6 mt-5 px-[16px]">
                    {Array.isArray(news) && news.length > 0 ? (
                        news.map((list, index) => (
                            <div className="max-w-sm bg-white min-h-[470px]" key={index}>
                                <div className="h-[200px]">
                                    {list.thumbnail?.path ? (
                                        <img
                                            className="w-full h-full object-cover"
                                            src={`/storage/${list.thumbnail.path}`}
                                            alt="News Thumbnail"
                                        />
                                    ) : (
                                        <div className="w-full h-full flex justify-center items-center bg-gray-100">
                                            <img src={NoImage} alt="No Image" className="h-[100px] w-[80px]" />
                                        </div>
                                    )}
                                </div>
                                <div className="py-5">
                                    <p className="text-xs mb-3 text-light-blue flex items-center gap-[4px]">
                                        <span>
                                            <svg viewBox="0 0 16 16" width="1em" height="1em" focusable="false" role="img" aria-label="calendar week" xmlns="http://www.w3.org/2000/svg" fill="currentColor" className="bi-calendar-week b-icon bi"><title>Date</title><g><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"></path><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"></path></g></svg>
                                        </span>
                                        {list.upload_date}
                                    </p>
                                    <h5 className="mb-2 text-sm font-bold tracking-tight text-primary truncate">
                                        <Link href={route("news.single", list.id)} className="hover:underline">
                                            {list.title}
                                        </Link>
                                    </h5>
                                    <p className="mb-3 font-normal">
                                        {limitWords(list.description, 30)}
                                    </p>
                                    <Link
                                        href={route("news.single", list.id)}
                                        className="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-primary bg-secondary rounded-lg hover:bg-blue-100 focus:ring-4 focus:outline-none"
                                    >
                                        Read more
                                        <svg
                                            className="rtl:rotate-180 w-3.5 h-3.5 ms-2"
                                            aria-hidden="true"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 14 10"
                                        >
                                            <path
                                                stroke="currentColor"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth="2"
                                                d="M1 5h12m0 0L9 1m4 4L9 9"
                                            />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="text-center p-5 text-gray-500">No news available.</div>
                    )}
                </Slider>
            </div>
        </div>
    );
};

export default News;
