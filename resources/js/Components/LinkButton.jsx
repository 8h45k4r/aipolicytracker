import { Link } from "@inertiajs/react";
import React from "react";

export default function LinkButton({ className, children, ...props }) {
    return (
        <Link
            {...props}
            className={`bg-secondary py-2 px-5 rounded-md text-primary font-bold  capitalize hover:bg-blue-100 ${className}`}
        >
            {children}
        </Link>
    );
}
