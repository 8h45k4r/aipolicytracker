import React, { useState } from "react";

export default function Model({
    isOpen,
    onClose,
    title = "",
    width = "",
    children,
}) {
    if (!isOpen) return null;

    return (
        <div
            id="default-modal"
            tabIndex="-1"
            aria-hidden="true"
            className="fixed inset-0 z-50 flex justify-center items-center w-full h-screen bg-black bg-opacity-50"
        >
            <div className={`relative p-4 w-full ${width} overflow-y-auto  max-h-full custom-scrollbar`}>
                {/* Modal content */}
                <div className="relative bg-white rounded-lg shadow">
                    {/* Modal header */}
                    <div className="flex items-center justify-between p-4 md:p-2 md:px-5 border-b rounded-t">
                        <h3 className="text-sm font-semibold text-gray-700">
                            {title}
                        </h3>
                        <button
                            type="button"
                            onClick={onClose}
                            className="bg-secondary hover:bg-blue-100 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center"
                        >
                            <svg
                                className="w-3 h-3"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 14 14"
                            >
                                <path
                                    stroke="#4E87D3"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"
                                />
                            </svg>
                            <span className="sr-only">Close modal</span>
                        </button>
                    </div>
                    {/* Modal body */}
                    <div className="p-4 md:p-5 space-y-4 overflow-y-auto">
                        {children}
                    </div>
                    {/* Modal footer */}
                    {/* <div className="flex items-center p-4 md:p-2 border-t border-gray-200 rounded-b dark:border-gray-600">
                        <button
                            onClick={onClose}
                            className="text-secondary bg-secondary hover:bg-blue-100 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                            type="button"
                        >
                            I accept
                        </button>
                        <button
                            onClick={onClose}
                            className="py-2.5 px-5 ms-3 text-sm font-medium text-gray-700 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 focus:z-10"
                            type="button"
                        >
                            Decline
                        </button>
                    </div> */}
                </div>
            </div>
        </div>
    );
}
