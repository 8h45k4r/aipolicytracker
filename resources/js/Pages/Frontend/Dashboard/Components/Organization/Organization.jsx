import React, { useRef } from "react";
import { Link } from "@inertiajs/react";
import Slider from "react-slick";
import "slick-carousel/slick/slick.css";
import "slick-carousel/slick/slick-theme.css";
import tdnepal from "@/assets/images/T4DNepal.png";

export default function Organization({ organizationLogo }) {
    const org = [1];
    // const org = [1, 2, 3, 4];

    // const settings = {
    //     dots: false,
    //     // infinite: true,
    //     infinite: organizationLogo.length > 5 ? true : false,
    //     // slidesToShow: 1,
    //     slidesToShow: 4,
    //     slidesToScroll: 1,
    //     autoplay: true,
    //     autoplaySpeed: 3000,
    //     pauseOnHover: true,
    //     adaptiveHeight: false,
    //     arrows: false,
    // };

    const settings = {
        dots: false, // Show dots for navigation
        // infinite: true, // Loop the slider
        // infinite: organizationLogo.length > 5, // Loop the slider

        infinite: organizationLogo.length > 5 ? true : false,

        speed: 500,
        slidesToShow: 5,
        slidesToScroll: 1,
        responsive: [
            {
                breakpoint: 1024,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 1,
                },
            },
            {
                breakpoint: 600,
                settings: {
                    slidesToShow: 1,
                    slidesToScroll: 1,
                },
            },
        ],
        autoplay: true,
        autoplaySpeed: 3000,
        pauseOnHover: true,
        adaptiveHeight: false,
        arrows: false,
    };
    return (
        <div className="bg-white h-fit p-5 rounded-md w-full mt-6">
            <div className="flex justify-between items-center pb-4">
                <div>
                    <h1 className="text-base lg:text-xl font-bold mb-2 text-primary-light">
                        Contributing Organizations
                    </h1>
                </div>
            </div>
            {organizationLogo.length > 1 ? (
                <Slider {...settings}>
                    {organizationLogo.map((data, index) => (
                        <a
                            key={index}
                            href={data?.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="w-fit border p-2 hover:border-blue-300"
                        >
                            <img
                                src={`/storage/${data?.file_path}`}
                                alt={data?.name}
                                className="object-contain w-12 h-12 mx-auto" // Set a fixed width and height for logos
                            />
                        </a>
                    ))}
                </Slider>
            ) : (
                organizationLogo.length > 0 ? (
                <div className="w-fit">
                    <a
                        href={organizationLogo[0]?.url}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <img
                            src={`/storage/${organizationLogo[0]?.file_path}`}
                            alt={organizationLogo[0]?.name}
                            className="object-contain w-12 h-12 mx-auto" // Set a fixed width and height for a single logo
                        />
                    </a>
                </div>
                ):
                (
                    <p>Not Any Organization</p>
                )
            )}
        </div>
    );
}
