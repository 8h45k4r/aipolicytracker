import React from "react";

export default function InputError({ message = null, ...props }) {
    return (
        <div>
            <div className="text-sm text-state-bad">{message}</div>
        </div>
    );
}
