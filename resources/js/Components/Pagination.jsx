import { router } from "@inertiajs/react";
import React from "react";
import Button from "./Button";

export default function Pagination({ paginator }) {
    const handlePageChange = (url) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    const currentPage = paginator.current_page;
    const lastPage = paginator.last_page;
    const maxButtons = 5;

    // Determine the range of pages to display
    const getPaginationRange = () => {
        let start = Math.max(currentPage - Math.floor(maxButtons / 2), 1);
        let end = Math.min(start + maxButtons - 1, lastPage);

        if (end - start + 1 < maxButtons) {
            start = Math.max(end - maxButtons + 1, 1);
        }

        return { start, end };
    };

    const { start, end } = getPaginationRange();
    const pages = [...Array(end - start + 1).keys()].map((num) => start + num);

    return (
        <div className="flex flex-col">
            {/* Pagination Controls */}
            <div className="flex justify-between mt-4">
                <Button
                    onClick={() => handlePageChange(paginator.prev_page_url)}
                    disabled={!paginator.prev_page_url}
                    className={`py-2 px-5 rounded-md ${
                        currentPage === 1
                            ? "bg-gray-200 text-black hover:bg-blue-100"
                            : "bg-secondary hover:bg-blue-100"
                    }`}
                >
                    Previous
                </Button>

                {/* Page Numbers */}
                <div className="flex gap-2">
                    {start > 1 && (
                        <>
                            <Button
                                onClick={() => handlePageChange(`?page=1`)}
                                className="py-2 px-4 bg-gray-200 text-black hover:bg-blue-100"
                            >
                                1
                            </Button>
                            {start > 2 && (
                                <Button className="py-2 px-4 bg-gray-200 text-black">
                                    ...
                                </Button>
                            )}
                        </>
                    )}

                    {pages.map((page) => (
                        <Button
                            key={page}
                            onClick={() => handlePageChange(`?page=${page}`)}
                            className={`py-2 px-4 ${
                                currentPage === page
                                    ? "bg-secondary text-black"
                                    : "bg-gray-200 text-black hover:bg-blue-100"
                            }`}
                        >
                            {page}
                        </Button>
                    ))}

                    {end < lastPage && (
                        <>
                            {end < lastPage - 1 && (
                                <Button className="py-2 px-4 bg-gray-200 text-black">
                                    ...
                                </Button>
                            )}
                            <Button
                                onClick={() =>
                                    handlePageChange(`?page=${lastPage}`)
                                }
                                className="py-2 px-4 bg-gray-200 text-black hover:bg-blue-100"
                            >
                                {lastPage}
                            </Button>
                        </>
                    )}
                </div>

                <Button
                    onClick={() => handlePageChange(paginator.next_page_url)}
                    disabled={!paginator.next_page_url}
                    className={`py-2 px-5 rounded-md ${
                        currentPage === lastPage
                            ? "bg-gray-200 text-black hover:bg-blue-100"
                            : "bg-secondary hover:bg-blue-100"
                    }`}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
