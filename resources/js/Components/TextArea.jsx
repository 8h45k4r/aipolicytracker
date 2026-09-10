import React from "react";

export default function TextArea({
    placeholder = "Write your thoughts here...",
    htmlFor = "",
    rows = null,
    cols = null,
    label = "",
    ...props
}) {
    return (
        <div className="mb-5">
            <label
                htmlFor={htmlFor}
                className="block tracking-wide text-gray-500 text-sm font-bold mb-2"
            >
                {label}
            </label>
            <textarea
                id={htmlFor}
                rows={rows}
                cols={cols}
                className="input-class border border-gray-300 text-gray-900 text-sm rounded focus:ring-blue-500 focus:border-blue-500 block w-full px-2.5"
                placeholder={placeholder}
                {...props}
            ></textarea>
        </div>
    );
}
