import { router } from "@inertiajs/react";
import React from "react";
import Button from "../Button";

export default function PaginationPage({ paginator }) {
    const handlePageChange = (url) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    const currentPage = paginator.current_page;
    const lastPage = paginator.last_page;
    const maxButtons = 4;

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
        <div className="flex flex-col mt-2 mb-5">
            {/* Pagination Controls */}
            <div className="flex mt-4 justify-center">
                <div className="flex">
                    {/* First Page Button */}
                    <Button
                        onClick={() => handlePageChange(`?page=1`)}
                        disabled={currentPage === 1}
                        className={`py-2 px-3 rounded-md  ${currentPage === 1
                            ? "text-light-blue"
                            : "text-light-blue hover:bg-blue-100"
                            }`}
                    >
                        &laquo;
                    </Button>

                    {/* Previous Page Button */}
                    <Button
                        onClick={() => handlePageChange(paginator.prev_page_url)}
                        disabled={!paginator.prev_page_url}
                        className={`py-2 px-3 rounded-md ${currentPage === 1
                            ? "text-light-blue"
                            : "text-light-blue hover:bg-blue-100"
                            }`}
                    >
                        &#x3C;
                    </Button>

                    {/* Page Numbers */}
                    <div className="flex gap-2">
                        {start > 1 && (
                            <>
                                <Button
                                    onClick={() => handlePageChange(`?page=1`)}
                                    className="py-2 px-3 bg-gray-200 text-light-blue hover:bg-blue-100"
                                >
                                    1
                                </Button>
                                {start > 2 && (
                                    <Button className="py-2 px-3 bg-gray-200 text-light-blue">
                                        ...
                                    </Button>
                                )}
                            </>
                        )}

                        {pages.map((page) => (
                            <Button
                                key={page}
                                onClick={() => handlePageChange(`?page=${page}`)}
                                className={`py-2 px-3 ${currentPage === page
                                    ? "bg-secondary text-light-blue"
                                    : "bg-gray-200 text-light-blue hover:bg-blue-100"
                                    }`}
                            >
                                {page}
                            </Button>
                        ))}

                        {end < lastPage && (
                            <>
                                {end < lastPage - 1 && (
                                    <Button className="py-2 px-3 bg-gray-200 text-light-blue">
                                        ...
                                    </Button>
                                )}
                                <Button
                                    onClick={() =>
                                        handlePageChange(`?page=${lastPage}`)
                                    }
                                    className="py-2 px-3 bg-gray-200 text-light-blue hover:bg-blue-100"
                                >
                                    {lastPage}
                                </Button>
                            </>
                        )}
                    </div>

                    {/* Next Page Button */}
                    <Button
                        onClick={() => handlePageChange(paginator.next_page_url)}
                        disabled={!paginator.next_page_url}
                        className={`py-2 px-3 rounded-md ${currentPage === lastPage
                            ? " text-light-blue"
                            : "text-light-blue hover:bg-blue-100"
                            }`}
                    >
                        &gt;
                    </Button>

                    {/* Last Page Button */}
                    <Button
                        onClick={() => handlePageChange(`?page=${lastPage}`)}
                        disabled={currentPage === lastPage}
                        className={`py-2 px-3 rounded-md ${currentPage === lastPage
                            ? " text-light-blue"
                            : "text-light-blue hover:bg-blue-100"
                            }`}
                    >
                        &raquo;
                    </Button>
                </div>
            </div>
        </div>
    );
}
