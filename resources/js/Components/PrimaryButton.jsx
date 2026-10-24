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
                    `ms-4 px-5 py-1 rounded-md text-white font-bold bg-brand-navy hover:bg-brand-ink focus:ring-2 focus:ring-brand-cyan ` +
                    className
                }
            >
                {children}
            </button>
        </div>
    );
}
