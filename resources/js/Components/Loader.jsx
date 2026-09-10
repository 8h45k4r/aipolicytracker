import React from "react";

export default function Loader() {
    return (
        <div className="flex justify-center items-center my-4 w-full h-[400px]">
            <div className="loader border-t-4 border-blue-500 rounded-full w-8 h-8 animate-spin"></div>
        </div>
    );
}
