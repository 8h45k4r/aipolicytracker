import React from "react";

export default function PrimaryButton({
    className = "",
    children = "",
    ...props
}) {
    return (
        <div>
            <button
                {...props}
                className={
                    `ms-4 px-5 py-1 rounded-md text-white font-bold bg-blue-600 hover:bg-blue-500 ` +
                    className
                }
            >
                {children}
            </button>
        </div>
    );
}
