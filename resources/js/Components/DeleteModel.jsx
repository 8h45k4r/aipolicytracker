import { router } from "@inertiajs/react";
import React, { useState } from "react";

const DeleteModel = ({ onClose, isOpen, aiId, routePath, title }) => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    if (!isOpen) return null;

    const handleDelete = async () => {
        setLoading(true);
        setError(null);
        try {
            await router.delete(routePath+aiId);
            onClose(); // Close modal after deletion
        } catch (err) {
            setError("Failed to delete the item. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <div
                id="popup-modal"
                tabIndex="-1"
                className="fixed inset-0 z-50 flex justify-center items-center w-full h-full bg-gray-800 bg-opacity-50"
            >
                <div className="relative p-4 w-full max-w-md max-h-full">
                    <div className="relative bg-white rounded-lg shadow dark:bg-gray-700">
                        {/* Close Modal Button */}
                        <button
                            type="button"
                            onClick={onClose}
                            className="absolute top-3 right-2.5 text-gray-400 bg-secondary hover:bg-blue-100 rounded-lg text-sm w-8 h-8 inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        >
                            <svg
                                className="w-3 h-3"
                                aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 14 14"
                            >
                                <path
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"
                                />
                            </svg>
                            <span className="sr-only">Close modal</span>
                        </button>
                        {/* Modal Content */}
                        <div className="p-4 md:p-5 text-center">
                            <div className="w-full flex justify-center mt-5 mb-4">
                                <svg
                                    className="text-red-500 w-10 h-10 dark:text-gray-200"
                                    aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        stroke="currentColor"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                                    />
                                </svg>
                            </div>
                            <h3 className="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">
                                {title}
                            </h3>
                            {error && (
                                <p className="text-red-500 mb-4">{error}</p>
                            )}
                            <button
                                onClick={handleDelete}
                                type="button"
                                disabled={loading}
                                className={`text-white ${
                                    loading ? "bg-red-400" : "bg-red-600"
                                } hover:bg-red-800 font-medium rounded-lg text-sm inline-flex items-center px-5 py-2.5 text-center`}
                            >
                                {loading ? "Deleting..." : "Yes"}
                            </button>
                            <button
                                onClick={onClose}
                                type="button"
                                className="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-gray-200 rounded-lg border border-gray-200 hover:bg-gray-100"
                            >
                                No
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DeleteModel;
