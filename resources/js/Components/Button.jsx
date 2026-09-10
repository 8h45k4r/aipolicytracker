import PropTypes from "prop-types";
import React from "react";

export default function Button({
    className = "text-primary font-bold",
    children = "bg-secondary py-2 px-5 rounded-md hover:bg-blue-100",
    type = "button",
    ...props
}) {
    return (
        <button {...props} type={type} className={`rounded-md ${className}`}>
            {children}
        </button>
    );
}

// PropTypes validation
Button.propTypes = {
    className: PropTypes.string,
    children: PropTypes.node,
    type: PropTypes.oneOf(["button", "submit", "reset"]),
};
