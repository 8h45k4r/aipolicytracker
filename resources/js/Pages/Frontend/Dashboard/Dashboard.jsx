import React, { useState, useEffect } from "react";
import { MapChart } from "./Components/Map/MapChart";
import SelectInput from "@/Components/SelectInput";
import Status from "@/Components/Status/Status";
import Table from "@/Components/Table/Table";
import OriginalColumns from "@/Components/Table/Columns";
import EditColumn from "./Components/EditColumn";
import EditColumnLists from "./Components/EditColumnLists";
import News from "./Components/News/News";
import Organization from "./Components/Organization/Organization";
import axios from "axios";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import Input from "@/Components/Input";
import PaginationPage from "@/Components/Table/PaginationPage";
import { AppLayout } from "@/Layouts/AppLayout";

export default function Dashboard({
    news,
    aiPolicies,
    countries,
    statuses,
    tableData: initialTableData,
    aiPolicyLastUpdate,
    newsLastUpdate,
    countrywithStatus: initialCountrywithStatus,
    countryWithAiPolicies,
    authUserBookmarkCount,
    govAiIndex = [],
    organizationLogo
}) {
    var [countrywithStatus, setCountrywithStatus] = useState(
        initialCountrywithStatus
    );
    const [tableData, setTableData] = useState(initialTableData); // Initialize with the prop data

    const [statusState, setStatusState] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        // Update the state if the initialTableData prop changes
        setTableData(initialTableData);
    }, [initialTableData]);

    const [filters, setFilters] = useState({
        Gov_ai_index: [],
        AI_Policy_Name: [],
        country_id: [],
        status_id: [],
        // announcement_year: "",
    });

    const handleFilterChange = (name, selectedOptions) => {
        const value = selectedOptions
            ? selectedOptions.map((option) => option.value)
            : [];
        setFilters((prevFilters) => {
            const updatedFilters = {
                ...prevFilters,
                [name]: value,
            };
            fetchData(updatedFilters); // Fetch data after updating filters
            return updatedFilters;
        });
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFilters((prevFilters) => {
            const updatedFilters = {
                ...prevFilters,
                [name]: value,
            };
            fetchData(updatedFilters); // Fetch data after updating filters
            return updatedFilters;
        });
    };

    const fetchData = async (updatedFilters) => {
        setLoading(true);

        let timeoutId;

        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(async () => {
            try {
                const queryParams = new URLSearchParams(
                    updatedFilters
                ).toString();
                const response = await fetch(
                    `${route("frontend.dashboard.filtered")}?${queryParams}`,
                    {
                        method: "GET",
                        headers: {
                            "Content-Type": "application/json",
                        },
                    }
                );

                const result = await response.json();
                setTableData(result); // Update the tableData state
            } catch (error) {
                console.error("Error fetching filtered data:", error);
            } finally {
                setLoading(false);
            }
        }, 500);

        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    };

    //****************** Clear Filter ******************* */
    const handleClearFilters = () => {
        const clearedFilters = {
            Gov_ai_index: [],
            AI_Policy_Name: [],
            country_id: [],
            status_id: [],
            // announcement_year: "",
        };

        setFilters(clearedFilters);
        fetchData(clearedFilters); // Fetch data with cleared filters
    };

    // edit column
    const [checkedColumns, setCheckedColumns] = useState([]);

    // Callback function to handle checkbox changes
    const handleCheckboxChange = (checkedItems) => {
        setCheckedColumns(checkedItems);
    };

    // console.log("dashboard checked column", checkedColumns);

    // Combine OriginalColumns with checkedColumns
    const Columns = [...OriginalColumns, ...checkedColumns];

    useEffect(() => {}, [Columns]);

    ///////////////
    // Function to handle status change

    const handleStatusChange1 = async (statusId, isChecked) => {
        try {
            // Update the local state first
            setStatusState((prevState) => {
                const updatedState = {
                    ...prevState,
                    [statusId]: isChecked,
                };

                // Prepare the query parameters by filtering out the boolean values
                // Prepare the query parameters by filtering out the boolean values
                const filteredState = Object.keys(updatedState).filter(
                    (key) => updatedState[key]
                );

                // Convert the filteredState into query parameters for array format
                const queryParams = new URLSearchParams();
                filteredState.forEach((statusId) => {
                    queryParams.append("status_state[]", statusId);
                });

                // Fetch CSRF token
                const csrfMetaTag = document.querySelector(
                    'meta[name="csrf-token"]'
                );
                const csrfToken = csrfMetaTag
                    ? csrfMetaTag.getAttribute("content")
                    : "";

                // Perform the request with the updated state
                axios
                    .get(
                        `${route(
                            "frontend.dashboard.updateStatus"
                        )}?${queryParams}`,
                        {
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrfToken,
                            },
                        }
                    )
                    .then((response) => {
                        if (response.status === 200) {
                            // i want to update countrywithStatus
                            const updatedCountrywithStatus = response.data;
                            // console.log(updatedCountrywithStatus);
                            setCountrywithStatus(updatedCountrywithStatus); // Update the state with the new data
                        } else {
                            // alert('Failed to update status');
                            // console.error("Failed to update status");
                        }
                    })
                    .catch((error) => {
                        setCountrywithStatus(initialCountrywithStatus);

                        // console.error("Error updating status:", error);
                    });

                // Return the updated state
                return updatedState;
            });
        } catch (error) {
            // alert('Failed to update status');
            console.error("Error updating status:", error);
        }
    };

    // Function to handle the "Show all" button click
    const handleShowAll = () => {
        const allCheckedState = statuses.reduce((acc, status) => {
            acc[status.value] = true; // Set all checkboxes to true (checked)
            return acc;
        }, {});

        // Update the status state for all checkboxes
        setStatusState(allCheckedState);

        // Optionally, trigger the handleStatusChange1 for each status to reflect this change in the backend
        statuses.forEach((status) => {
            handleStatusChange1(status.value, true);
        });
    };

    const hasData = Array.isArray(tableData.data) && tableData.data.length > 0;

    // State for showing advanced info
    const [isShowAdvancedInfo, setShowAdvancedInfo] = useState(null);

    // Function to toggle showing advanced info
    const toggleDropDownShowAdvancedInfo = (id) => {
        setShowAdvancedInfo((prevState) => (prevState === id ? null : id));
    };

    // filter drop down (mobile view)
    const [isOpenFilter, setOpenFilter] = useState(false);
    const toggleFilterDropDown = () => {
        setOpenFilter(!isOpenFilter);
    };

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="content-wrapper md:relative md:top-[-60px]">
                {/* desktop */}
                <div className="flex gap-[30px]">
                    <div className="w-full">
                        <div className="w-full border-0 md:border rounded-md bg-transparent md:bg-white">
                            <div className="lg:border-b border-light-border py-[16px] lg:px-[16px] flex justify-between items-center">
                                <div>
                                    <p className="font-bold text-primary-light text-lg leading-none">
                                        Global Artificial Intelligence (AI)
                                        Policy Status
                                    </p>
                                    <div className="block md:flex items-center mt-[0.5rem] text-sm">
                                        <p className="text-light-blue text-xs">
                                            Database Update:
                                            <span className="text-black ml-[4px]">
                                                {aiPolicyLastUpdate}
                                            </span>
                                        </p>
                                        <span className="date-separator hidden lg:block xl:block"></span>
                                        <p className="text-light-blue text-xs">
                                            News Update:
                                            <span className="text-black ml-[4px]">
                                                {newsLastUpdate}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                {/* ********************** Status Component (desktop) ********************** */}

                                <div className="hidden xl:flex xl:justify-center xl:items-center xl:mt-5 2xl:flex 2xl:justify-center 2xl:items-center 2xl:mt-5">
                                    <Status
                                        statuses={statuses}
                                        statusState={statusState}
                                        handleStatusChange1={
                                            handleStatusChange1
                                        }
                                        handleShowAll={handleShowAll}
                                    />
                                </div>
                            </div>

                            {/* ********************** Status Component (mobile) ********************** */}
                            <div className="hidden md:flex lg:flex xl:hidden justify-center mt-5 items-center">
                                <Status
                                    statuses={statuses}
                                    statusState={statusState}
                                    handleStatusChange1={handleStatusChange1}
                                    handleShowAll={handleShowAll}
                                />
                            </div>

                            {/* ********************** MapChart Component ********************** */}
                            <div className="hidden md:block mt-5 px-4 map-chart-wrapper">
                                <MapChart
                                    countrywithStatus={countrywithStatus}
                                    countryWithAiPolicies={
                                        countryWithAiPolicies
                                    }
                                />
                            </div>

                            {/* boookmarks, search, and editcolumn button */}
                            <div className="hidden flex-wrap gap-y-2 mt-5 px-5 md:flex">
                                {/* watchlist fav */}
                                <div className="flex-none">
                                    <Link
                                        href={route(
                                            "frontend.watch_list.index"
                                        )}
                                        className="text-primary-light h-fit bg-secondary hover:bg-blue-100 focus:ring-0 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center gap-2"
                                    >
                                        <i
                                            className={`fa ${
                                                authUserBookmarkCount
                                                    ? "fa-star"
                                                    : "fa-regular fa-star"
                                            } mr-3`}
                                        ></i>
                                        <span>
                                            Bookmarks ({authUserBookmarkCount})
                                        </span>
                                    </Link>
                                </div>

                                {/* search filter for table data */}

                                <div className="flex-grow">
                                    <form className="w-full" id="FormFiled">
                                        <div className="flex">
                                            <div className="w-full px-3">
                                                <SelectInput
                                                    placeholder="Gov Ai Index"
                                                    className="mb-2 text-sm font-normal text-light-blue"
                                                    value={filters.Gov_ai_index}
                                                    options={govAiIndex.map(
                                                        (govAiIndexName) => ({
                                                            value: govAiIndexName.value,
                                                            label: govAiIndexName.label,
                                                        })
                                                    )}
                                                    onChange={(
                                                        selectedOptions
                                                    ) =>
                                                        handleFilterChange(
                                                            "Gov_ai_index",
                                                            selectedOptions
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="w-full px-3">
                                                <SelectInput
                                                    placeholder="Name"
                                                    className="mb-2 text-sm font-normal text-light-blue"
                                                    options={aiPolicies.map(
                                                        (policy) => ({
                                                            value: policy.value,
                                                            label: policy.label,
                                                        })
                                                    )}
                                                    value={
                                                        filters.AI_Policy_Name
                                                    }
                                                    onChange={(
                                                        selectedOptions
                                                    ) =>
                                                        handleFilterChange(
                                                            "AI_Policy_Name",
                                                            selectedOptions
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="w-full px-3">
                                                <SelectInput
                                                    placeholder="Country / Region"
                                                    className="text-sm mb-2 font-normal text-light-blue"
                                                    options={countries.map(
                                                        (country) => ({
                                                            value: country.value,
                                                            label: country.label,
                                                        })
                                                    )}
                                                    value={filters.country_id}
                                                    onChange={(
                                                        selectedOptions
                                                    ) =>
                                                        handleFilterChange(
                                                            "country_id",
                                                            selectedOptions
                                                        )
                                                    }
                                                />
                                            </div>

                                            <div className="w-full px-3">
                                                <SelectInput
                                                    placeholder="Status"
                                                    className="mb-2 text-sm font-normal text-light-blue"
                                                    options={statuses.map(
                                                        (status) => ({
                                                            value: status.value,
                                                            label: status.label,
                                                        })
                                                    )}
                                                    value={filters.status_id}
                                                    onChange={(
                                                        selectedOptions
                                                    ) =>
                                                        handleFilterChange(
                                                            "status_id",
                                                            selectedOptions
                                                        )
                                                    }
                                                />
                                            </div>

                                            {/* <div className="w-full px-3">
                                                <Input
                                                    className="mb-2 text-sm font-normal text-light-blue"
                                                    name="announcement_year"
                                                    value={
                                                        filters.announcement_year
                                                    }
                                                    onChange={handleInputChange}
                                                    htmlFor="announcement_year"
                                                    placeholder="Announcement Year"
                                                    type="date"
                                                />
                                            </div> */}
                                        </div>
                                    </form>
                                </div>

                                {/* edit column drop down */}
                                <div className="flex-none">
                                    <EditColumn
                                        EditColumnLists={EditColumnLists}
                                        onCheckboxChange={handleCheckboxChange}
                                    />
                                </div>
                            </div>

                            {/* ********************** AI Policy Component ********************** */}

                            {/* Loader */}
                            {loading ? (
                                <div className="flex justify-center items-center my-4 h-[400px] w-full">
                                    <div className="loader border-t-4 border-blue-500 rounded-full w-8 h-8 animate-spin"></div>
                                </div>
                            ) : (
                                <Table
                                    columns={Columns}
                                    checkedColWithData={checkedColumns}
                                    tableData={tableData.data}
                                    govAiIndex={govAiIndex}
                                />
                            )}

                            <div className="hidden md:block ">
                                {tableData.total >= 10 && (
                                    <PaginationPage paginator={tableData} />
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* mobile */}

                <div className="mb-5 block md:hidden">
                    <div className="border rounded-md w-full bg-white sticky top-0">
                        <div className="border-b border-light-border py-[16px] px-[16px] flex justify-between items-center">
                            <div className="flex items-center justify-between w-full">
                                <p className="font-bold text-primary text-lg leading-none">
                                    Filters
                                </p>
                                <div className="flex">
                                    {isOpenFilter && (
                                        <button
                                            className="button-wthout-border flex items-center gap-2 text-light-blue"
                                            onClick={handleClearFilters}
                                        >
                                            <span className="ui-icon">
                                                <svg
                                                    viewBox="0 0 16 16"
                                                    width="1em"
                                                    height="1em"
                                                    focusable="false"
                                                    role="img"
                                                    aria-label="x square"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="#7997c4"
                                                    className="bi-x-square b-icon bi"
                                                >
                                                    <title>Clear</title>
                                                    <g>
                                                        <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"></path>
                                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                                    </g>
                                                </svg>
                                            </span>
                                            <span className="ui-clear-button_text">
                                                Clear
                                            </span>
                                        </button>
                                    )}

                                    <button
                                        onClick={toggleFilterDropDown}
                                        className="text-primary font-medium rounded-lg text-sm block md:hidden"
                                        type="button"
                                    >
                                        <i className="fa-solid ms-3 text-primary fa-angle-down"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        {isOpenFilter && (
                            <div className="px-[16px] my-5">
                                <form className="w-full" id="FormFiled">
                                    <div>
                                        <SelectInput
                                            label="Gov Ai Index"
                                            // placeholder="Gov Ai Index"
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            value={filters.Gov_ai_index}
                                            options={govAiIndex.map(
                                                (govAiIndexName) => ({
                                                    value: govAiIndexName.value,
                                                    label: govAiIndexName.label,
                                                })
                                            )}
                                            onChange={(selectedOptions) =>
                                                handleFilterChange(
                                                    "Gov_ai_index",
                                                    selectedOptions
                                                )
                                            }
                                        />

                                        <SelectInput
                                            label="Name"
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            options={aiPolicies.map(
                                                (policy) => ({
                                                    value: policy.value,
                                                    label: policy.label,
                                                })
                                            )}
                                            value={filters.AI_Policy_Name}
                                            onChange={(selectedOptions) =>
                                                handleFilterChange(
                                                    "AI_Policy_Name",
                                                    selectedOptions
                                                )
                                            }
                                        />

                                        <SelectInput
                                            label="Country / Region"
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            options={countries.map(
                                                (country) => ({
                                                    value: country.value,
                                                    label: country.label,
                                                })
                                            )}
                                            value={filters.country_id}
                                            onChange={(selectedOptions) =>
                                                handleFilterChange(
                                                    "country_id",
                                                    selectedOptions
                                                )
                                            }
                                        />

                                        <SelectInput
                                            label="Status"
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            options={statuses.map((status) => ({
                                                value: status.value,
                                                label: status.label,
                                            }))}
                                            value={filters.status_id}
                                            onChange={(selectedOptions) =>
                                                handleFilterChange(
                                                    "status_id",
                                                    selectedOptions
                                                )
                                            }
                                        />

                                        <Input
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            name="announcement_year"
                                            value={filters.announcement_year}
                                            onChange={handleInputChange}
                                            htmlFor="announcement_year"
                                            label="Announcement Year"
                                            type="date"
                                        />
                                    </div>
                                </form>
                            </div>
                        )}
                    </div>
                </div>
                {/* mobile */}
                <div className="block md:hidden">
                    {tableData.data.map((list) => (
                        <div
                            className="bg-white rounded pb-3 mb-5 block md:hidden"
                            key={list.id}
                        >
                            <div className="flex flex-wrap justify-between py-3 px-5 bg-blue-100">
                                <div>
                                    <p className="font-bold text-primary-light text-base w-full leading-none">
                                        <Link
                                            href={route(
                                                "frontend.single_ai_policy_tracker.index",
                                                { id: list.id }
                                            )}
                                            className="hover:underline"
                                        >
                                            {list.ai_policy_name}
                                        </Link>

                                        <Link
                                            href={route(
                                                "frontend.watch_list.add",
                                                {
                                                    id: list.id,
                                                    isBooked: list.bookmark
                                                        ?.ai_policy_tracker_id
                                                        ? true
                                                        : false,
                                                }
                                            )}
                                            className="text-primary-light ms-3"
                                        >
                                            {list.bookmark
                                                ?.ai_policy_tracker_id ? (
                                                <i className="fa fa-star A"></i>
                                            ) : (
                                                <i className="fa-regular fa-star B"></i>
                                            )}
                                        </Link>
                                        {/* <i className="fa-regular fa-star ms-3"></i> */}
                                    </p>
                                    <p className="mt-2">{list.status?.name}</p>
                                </div>
                                <div>
                                    <Link
                                        href={route(
                                            "frontend.single_ai_policy_tracker.index",
                                            { id: list.id }
                                        )}
                                    >
                                        <i className="fa-regular fa-circle-right"></i>
                                    </Link>
                                </div>
                            </div>

                            <div className="px-5">
                                <div className="py-3">
                                    <div>
                                        <p className="text-muted-light font-semibold text-sm">
                                            Country
                                        </p>
                                    </div>
                                    <div className="flex mt-1 gap-3 items-center text-sm">
                                        <p className="text-gray-500">
                                            {list.country?.name}
                                        </p>
                                    </div>
                                </div>
                                <div className="py-3">
                                    <div>
                                        <p className="text-muted-light font-semibold text-sm">
                                            Governing Body
                                        </p>
                                    </div>
                                    <div className="flex mt-1 gap-3 items-center text-sm">
                                        <p className="text-gray-500">
                                            {list.governing_body}
                                        </p>
                                    </div>
                                </div>

                                <div className="py-3">
                                    <div>
                                        <p className="text-muted-light font-semibold text-sm">
                                            Announcement Year
                                        </p>
                                    </div>
                                    <div className="flex mt-1 gap-3 items-center text-sm">
                                        <p className="text-gray-500">
                                            {list.formatted_created_at}
                                        </p>
                                    </div>
                                </div>

                                <div className="py-3">
                                    <div>
                                        <p className="text-muted-light font-semibold text-sm">
                                            Technology Partner
                                        </p>
                                    </div>
                                    <div className="flex mt-1 gap-3 items-center text-sm">
                                        <p className="text-gray-500">
                                            {list.technology_partners}
                                        </p>
                                    </div>
                                </div>

                                {list.id == isShowAdvancedInfo && (
                                    <div>
                                        <div className="py-3">
                                            <div>
                                                <p className="text-muted-light font-semibold text-sm">
                                                    Governance structure
                                                </p>
                                            </div>
                                            <div className="flex mt-1 gap-3 items-center text-sm">
                                                <p className="text-gray-500">
                                                    {list.governance_structure}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="py-3">
                                            <div>
                                                <p className="text-muted-light font-semibold text-sm">
                                                    Main motivation/goals of the
                                                    AI policy
                                                </p>
                                            </div>
                                            <div className="flex mt-1 gap-3 items-center text-sm">
                                                <p className="text-gray-500">
                                                    {list.main_motivation}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="py-3">
                                            <div>
                                                <p className="text-muted-light font-semibold text-sm">
                                                    Description
                                                </p>
                                            </div>
                                            <div
                                                className="mt-1 text-sm text-gray-500"
                                                dangerouslySetInnerHTML={{
                                                    __html: list.description,
                                                }}
                                            ></div>
                                        </div>
                                    </div>
                                )}
                            </div>
                            <div className="border-b"></div>
                            <div className="pt-3 px-5 flex justify-center items-center">
                                <button
                                    onClick={() =>
                                        toggleDropDownShowAdvancedInfo(list.id)
                                    }
                                    className="text-primary"
                                    type="button"
                                >
                                    <span>
                                        <i
                                            className={` fa-solid ms-3 ${
                                                list.id == isShowAdvancedInfo
                                                    ? "fa-angle-up"
                                                    : "fa-angle-down"
                                            }`}
                                        ></i>
                                    </span>
                                    <span className="ms-1">
                                        {list.id == isShowAdvancedInfo
                                            ? "Hide adanced info"
                                            : "    Show advanced info"}
                                    </span>
                                </button>
                            </div>
                        </div>
                    ))}

                    {tableData.total >= 10 && (
                        <PaginationPage paginator={tableData} />
                    )}

                    {!hasData && (
                        <h1 className="font-bold text-primary text-lg text-center">
                            Not Found
                        </h1>
                    )}
                </div>

                {/* ********************** News Component ********************** */}

                <div className="hidden md:block">
                    <News news={news} />
                </div>

                {/* ********************** Organization/Client Component ********************** */}
                <Organization organizationLogo={organizationLogo} />
            </div>
        </AppLayout>
    );
}
