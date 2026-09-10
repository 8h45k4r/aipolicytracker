import React from "react";

export default function ({
    label = "",
    type = "text",
    placeholder = "",
    htmlFor = "",
    className = "",
    errorMsg = null,
    ...props
}) {
    return (
        <div className={className}>
            {label && (
                <label
                    htmlFor={htmlFor}
                    className={`block tracking-wide text-gray-500 text-sm font-bold mb-2 ${className}`}
                >
                    {label}
                </label>
            )}

            <input
                type={type}
                id={htmlFor}
                className="input-class border border-gray-300 text-gray-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 block w-full px-2.5"
                placeholder={placeholder}
                {...props}
            />
            {errorMsg && (
                <p className="text-red-500 text-xs mt-1">{errorMsg}</p>
            )}
        </div>
    );
}
