import { Link } from "@inertiajs/react";
import React from "react";

export default function Contact({ contactLists = [] }) {
    return (
        <div className="w-full mt-5 md:mt-0 sm:mt-0 md:w-1/2 sm:w-1/2">
            <strong>Contacts</strong>
            <div className="mt-3 h-fit w-full">
                {contactLists.map((data, index) => (
                    <a
                        href={data.url}
                        className="flex gap-2 items-center hover:underline text-light-blue"
                        key={index}
                    >
                        <i className={data.icon}></i>
                        <span>{data.name}</span>
                    </a>
                ))}
            </div>
        </div>
    );
}
