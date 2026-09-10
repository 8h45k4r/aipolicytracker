import React, { useState, useEffect } from "react";
import { AppLayout } from "@/Layouts/AppLayout";
import { Head, Link } from "@inertiajs/react";
import Table from "@/Components/Table/Table";
import Columns from "@/Components/Table/Columns";
import Subscription from "./Components/Subscription";
import PaginationPage from "@/Components/Table/PaginationPage";
import SelectInput from "@/Components/SelectInput";
import Input from "@/Components/Input";
import EditColumn from "../Dashboard/Components/EditColumn";
import EditColumnLists from "../Dashboard/Components/EditColumnLists";
import Responsive from "@/Components/Responsive/Responsive";

export default function WatchList({
    is_favorite,
    aiPolicies,
    countries,
    statuses,
    tableData: initialTableData,
    govAiIndex = [],
}) {
    const [tableData, setTableData] = useState(initialTableData); // Initialize with the prop data

    useEffect(() => {
        // Update the state if the initialTableData prop changes
        setTableData(initialTableData);
    }, [initialTableData]);

    const [filters, setFilters] = useState({
        Gov_ai_index: [],
        AI_Policy_Name: [],
        country_id: [],
        status_id: [],
        announcement_year: "",
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
        try {
            const queryParams = new URLSearchParams(updatedFilters).toString();
            const response = await fetch(
                `${route("frontend.watch_list.filtered")}?${queryParams}`,
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
        }
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

    // for mobile view (filter box)
    const [isDropdownOpen, setDropdownOpen] = useState(false);
    const toggleDropDown = () => {
        setDropdownOpen(!isDropdownOpen);
    };

    // State for showing advanced info
    const [isShowAdvancedInfo, setShowAdvancedInfo] = useState(null);

    // Function to toggle showing advanced info
    const toggleDropDownShowAdvancedInfo = (id) => {
        setShowAdvancedInfo((prevState) => (prevState === id ? null : id));
    };

    // table if
    const hasData = Array.isArray(tableData.data) && tableData.data.length > 0;

    return (
        <AppLayout>
            <Head title="Watch List" />
            <div className="content-wrapper mt-5 xl:mt-0 lg:mt-0 md:relative md:top-[-60px]">
                <div className="block sm:block md:block xl:flex gap-[30px]">
                    {/* Sidebar for filters for desktop  */}
                    {/* <Responsive responsive={["xl"]}>
                    <div className="order-1 mb-5 xl:mt-0 lg:mt-0 xl:order-2 hidden xl:block  xl:w-[16.67%]">
                        <div className="border rounded-md w-full bg-white sticky top-0">
                            <div className="border-b border-light-border py-[16px] px-[16px] flex justify-between items-center">
                                <div className="flex items-center justify-between w-full">
                                    <p className="font-bold text-primary-light text-lg leading-none">
                                        Filters
                                    </p>
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
                                                fill="currentColor"
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
                                </div>
                            </div>
                            <div className="px-[16px] my-5">
                                <form className="w-full" id="FormFiled">
                                    <div>
                                        <SelectInput
                                            label="AI Policy Name"
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
                                            options={statuses.map(
                                                (status) => ({
                                                    value: status.value,
                                                    label: status.label,
                                                })
                                            )}
                                            value={filters.status_id}
                                            onChange={(selectedOptions) =>
                                                handleFilterChange(
                                                    "status_id",
                                                    selectedOptions
                                                )
                                            }
                                        />

                                        <Input
                                            name="announcement_year"
                                            className="mb-2 text-sm font-normal text-light-blue"
                                            value={
                                                filters.announcement_year
                                            }
                                            onChange={handleInputChange}
                                            htmlFor="announcement_year"
                                            label="Announcement Year"
                                            type="date"
                                        />
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </Responsive> */}
                    {/* News contenet only Desktop */}
                    <div className="w-full order-2 xl:order-1">
                        <div className="w-full  md:border rounded-md bg-transparent md:bg-white lg:bg-white">
                            <div className="lg:border-b border-light-border lg:p-[16px]  pb-4 lg:px-5 flex justify-between items-center">
                                <div className="w-full">
                                    <div className="flex justify-between px-[0] md:px-[16px]  xl:block">
                                        <div>
                                            <div>
                                                <p className="font-bold text-primary-light text-lg">
                                                    Bookmarks
                                                </p>
                                            </div>
                                            <div className="flex gap-5 mt-3 text-sm">
                                                <Link
                                                    href={"/"}
                                                    className=" text-sm flex gap-2 hover:underline"
                                                >
                                                    <span>
                                                        <i className="fa-regular fa-circle-left"></i>
                                                    </span>
                                                    <span>
                                                        Back to full list of
                                                        (AI) Policy
                                                    </span>
                                                </Link>
                                            </div>
                                        </div>
                                        {/* md and lg for show and hide search filter button */}
                                        {/* <Responsive responsive={["md", "lg"]}>
                                        <div className="flex gap-3 items-center">
                                            <div className="flex gap-4">
                                                {isDropdownOpen && (
                                                    <button
                                                        className="button-wthout-border flex items-center gap-2 text-blue"
                                                        onClick={
                                                            handleClearFilters
                                                        }
                                                    >
                                                        <svg
                                                            viewBox="0 0 16 16"
                                                            width="1em"
                                                            height="1em"
                                                            fill="currentColor"
                                                            className="bi-x-square b-icon bi"
                                                        >
                                                            <title>
                                                                Clear
                                                            </title>
                                                            <g>
                                                                <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"></path>
                                                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                                            </g>
                                                        </svg>
                                                        <span>Clear</span>
                                                    </button>
                                                )}

                                                <button
                                                    onClick={toggleDropDown}
                                                    className="text-primary font-medium rounded-lg text-sm block"
                                                    type="button"
                                                >
                                                    Show filters
                                                    <i className="fa-solid ms-3 text-primary fa-angle-down"></i>
                                                </button>
                                            </div>

                                            <div className="">
                                                <EditColumn
                                                    EditColumnLists={
                                                        EditColumnLists
                                                    }
                                                    onCheckboxChange={
                                                        handleCheckboxChange
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </Responsive> */}
                                    </div>
                                </div>
                            </div>

                            {/* show on small devices only (show and hide filter button) */}
                            <Responsive responsive={["sm"]}>
                                <div className="border-b border-light-border p-[16px] pb-4 flex justify-between items-center bg-white mt-4 rounded">
                                    <div>
                                        <p className="font-bold text-primary text-lg">
                                            Filters
                                        </p>
                                    </div>

                                    <div className="flex gap-4">
                                        {isDropdownOpen && (
                                            <button
                                                className="button-wthout-border flex items-center gap-2 text-blue"
                                                onClick={handleClearFilters}
                                            >
                                                <svg
                                                    viewBox="0 0 16 16"
                                                    width="1em"
                                                    height="1em"
                                                    fill="currentColor"
                                                    className="bi-x-square b-icon bi"
                                                >
                                                    <title>Clear</title>
                                                    <g>
                                                        <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"></path>
                                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                                                    </g>
                                                </svg>
                                                <span>Clear</span>
                                            </button>
                                        )}
                                        <button
                                            onClick={toggleDropDown}
                                            className="text-primary font-medium rounded-lg text-sm block"
                                            type="button"
                                        >
                                            <i className="fa-solid ms-3 text-primary fa-angle-down"></i>
                                        </button>
                                    </div>
                                </div>
                            </Responsive>

                            {/* /* show on small ,medium and lg devices for select filter form */}
                            <Responsive responsive={["sm", "md", "lg"]}>
                                {isDropdownOpen && (
                                    <div className="px-5 w-full pt-5 bg-white border-b xl:border-b-0 border-light-border">
                                        <form
                                            className="w-full"
                                            id="filterData"
                                        >
                                            <div className="flex flex-wrap -mx-3 mb-6">
                                                <div className="w-full md:w-1/2 px-3">
                                                    <SelectInput
                                                        label="Gov Ai Index"
                                                        // placeholder="Gov Ai Index"
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        value={
                                                            filters.Gov_ai_index
                                                        }
                                                        options={govAiIndex.map(
                                                            (
                                                                govAiIndexName
                                                            ) => ({
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

                                                    <SelectInput
                                                        label="Name"
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
                                                <div className="w-full md:w-1/2 px-3">
                                                    <SelectInput
                                                        label="Country / Region"
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        options={countries.map(
                                                            (country) => ({
                                                                value: country.value,
                                                                label: country.label,
                                                            })
                                                        )}
                                                        value={
                                                            filters.country_id
                                                        }
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
                                                <div className="w-full md:w-1/2 px-3">
                                                    <SelectInput
                                                        label="Status"
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        options={statuses.map(
                                                            (status) => ({
                                                                value: status.value,
                                                                label: status.label,
                                                            })
                                                        )}
                                                        value={
                                                            filters.status_id
                                                        }
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
                                                {/* <div className="w-full md:w-1/2 px-3">
                                                    <Input
                                                        name="announcement_year"
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        value={
                                                            filters.announcement_year
                                                        }
                                                        onChange={
                                                            handleInputChange
                                                        }
                                                        htmlFor="announcement_year"
                                                        label="Announcement Year"
                                                        type="date"
                                                    />
                                                </div> */}
                                            </div>
                                        </form>
                                    </div>
                                )}
                            </Responsive>

                            {/* edit and search filter column show on desktop */}
                            <Responsive responsive={["md", "lg", "xl"]}>
                                {/* search filter for table data */}
                                <div className="flex flex-wrap mt-5 px-5">
                                    <div className="flex-grow">
                                        <form className="w-full" id="FormFiled">
                                            <div className="flex">

                                            <div className="w-full px-3">
                                                    <SelectInput
                                                        placeholder="Gov Ai Index"
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        value={
                                                            filters.Gov_ai_index
                                                        }
                                                        options={govAiIndex.map(
                                                            (
                                                                govAiIndexName
                                                            ) => ({
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
                                                        value={
                                                            filters.country_id
                                                        }
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
                                                        value={
                                                            filters.status_id
                                                        }
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
                                                {/*
                                                <div className="w-full px-3">
                                                    <Input
                                                        className="mb-2 text-sm font-normal text-light-blue"
                                                        name="announcement_year"
                                                        value={
                                                            filters.announcement_year
                                                        }
                                                        onChange={
                                                            handleInputChange
                                                        }
                                                        htmlFor="announcement_year"
                                                        placeholder="Announcement Year"
                                                        type="date"
                                                    />
                                                </div> */}
                                            </div>
                                        </form>
                                    </div>
                                    {/* <div className="mt-5 flex justify-end px-5">
                                    <EditColumn
                                        EditColumnLists={EditColumnLists}
                                        onCheckboxChange={
                                            handleCheckboxChange
                                        }
                                    />
                                </div> */}
                                    <div className="flex-none">
                                        <EditColumn
                                            EditColumnLists={EditColumnLists}
                                            onCheckboxChange={
                                                handleCheckboxChange
                                            }
                                        />
                                    </div>
                                </div>
                            </Responsive>

                            {/* show on desktop (table) */}
                            <Responsive responsive={["md", "lg", "xl"]}>
                                <Table
                                    columns={Columns}
                                    tableData={tableData.data}
                                    favourite={is_favorite}
                                    checkedColWithData={checkedColumns}
                                />
                            </Responsive>
                            {/* show on small device table data (ai plicy data) */}
                            <Responsive responsive={["sm"]}>
                                {/* content */}
                                {tableData.data.map((list) => (
                                    <div
                                        className="bg-white rounded pb-3 mt-5 block md:hidden"
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
                                                                isBooked: list
                                                                    .bookmark
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
                                                <p className="mt-2">
                                                    {list.status?.name}
                                                </p>
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
                                                        {
                                                            list.formatted_created_at
                                                        }
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
                                                        {
                                                            list.technology_partners
                                                        }
                                                    </p>
                                                </div>
                                            </div>

                                            {list.id == isShowAdvancedInfo && (
                                                <div>
                                                    <div className="py-3">
                                                        <div>
                                                            <p className="text-muted-light font-semibold text-sm">
                                                                Governance
                                                                structure
                                                            </p>
                                                        </div>
                                                        <div className="flex mt-1 gap-3 items-center text-sm">
                                                            <p className="text-gray-500">
                                                                {
                                                                    list.governance_structure
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="py-3">
                                                        <div>
                                                            <p className="text-muted-light font-semibold text-sm">
                                                                Main
                                                                motivation/goals
                                                                of the AI policy
                                                            </p>
                                                        </div>
                                                        <div className="flex mt-1 gap-3 items-center text-sm">
                                                            <p className="text-gray-500">
                                                                {
                                                                    list.main_motivation
                                                                }
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
                                                    toggleDropDownShowAdvancedInfo(
                                                        list.id
                                                    )
                                                }
                                                className="text-primary"
                                                type="button"
                                            >
                                                <span>
                                                    <i
                                                        className={` fa-solid ms-3 ${
                                                            list.id ==
                                                            isShowAdvancedInfo
                                                                ? "fa-angle-up"
                                                                : "fa-angle-down"
                                                        }`}
                                                    ></i>
                                                </span>
                                                <span className="ms-1">
                                                    {list.id ==
                                                    isShowAdvancedInfo
                                                        ? "Hide adanced info"
                                                        : "    Show advanced info"}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </Responsive>

                            {tableData.total >= 10 && (
                                <PaginationPage paginator={tableData} />
                            )}
                            {/* show on small device if table when empty */}
                            <Responsive responsive={["sm"]}>
                                {!hasData && (
                                    <h1 className="font-bold text-primary text-lg mt-5 text-center">
                                        Not Found
                                    </h1>
                                )}
                            </Responsive>
                        </div>
                    </div>
                </div>

                {/* Subscription */}
                {/* <Subscription className="" /> */}
            </div>
        </AppLayout>
    );
}
