import React from "react";

export default function InputError({ message = null, ...props }) {
    return (
        <div>
            <div className=" text-red-600">{message}</div>
        </div>
    );
}
