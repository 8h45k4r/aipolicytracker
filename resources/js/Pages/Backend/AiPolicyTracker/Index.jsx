import AddIcon from "@/Components/AddIcon";
import Button from "@/Components/Button";
import DeleteIcon from "@/Components/DeleteIcon";
import EditIcon from "@/Components/EditIcon";
import Model from "@/Components/Model";
import NoTableData from "@/Components/Table/NoTableData";
import ViewIcon from "@/Components/ViewIcon";
import Layout from "@/Layouts/Backend/Layout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import React, { useEffect, useState } from "react";
import Add from "./Components/Add";
import Pagination from "@/Components/Pagination";
import DeleteModel from "@/Components/DeleteModel";
import axios from "axios";
import Edit from "./Components/Edit";
import Input from "@/Components/Input";
import { useCallback } from "react";

export default function Index({
    countries = null,
    status = null,
    govAiIndex = [],
    tableData: initialTableData,
}) {
    // add modal
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);

    // edit modal
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);

    //delete modal
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

    // set id
    const [selectedAiId, setSelectedAiId] = useState(null);

    // set updating data
    const [updatingData, setUpdatingData] = useState(null);

    // intialize table data
    const [tableData, setTableData] = useState(initialTableData); // Initialize with the prop data
    // when table data search then show loading
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        // Update the state if the initialTableData prop changes
        setTableData(initialTableData);
    }, [initialTableData]);

    // add toggle func
    const toggleAddModal = () => setIsAddModalOpen(!isAddModalOpen);

    function openEditModal() {
        setIsEditModalOpen(!isEditModalOpen);
    }

    // edit toggle func
    const toggleEdiModal = async (id = null) => {
        if (!id) return;

        try {
            // Make a GET request to fetch data by ID
            const response = await axios.post(
                `/backend/aipolicytracker/update/${id}`
            );

            const updatedData = response.data.aiPolicyTracker; // Adjust according to your response structure

            if (updatedData) {
                // Set the updating data and open the modal if data is not null
                setUpdatingData(updatedData);
                setSelectedAiId(id);
                // setIsEditModalOpen(!isEditModalOpen);

                openEditModal();
            } else {
                // Handle case where data is null, e.g., show a warning or notification
            }
        } catch (error) {
            // Handle error case if needed
            console.error("Failed to fetch data:", error);
        }
    };

    // delete toggle func
    const toggleDeleteModal = (id = null) => {
        setSelectedAiId(id);
        setIsDeleteModalOpen(!isDeleteModalOpen);
    };

    // search of name
    const searchHandle = (value) => {
        fetchSearch(value);
    };

    const fetchSearch = useCallback(async (name) => {
        setLoading(true);

        let timeoutId;

        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        // Set new timeout for the API call
        timeoutId = setTimeout(async () => {
            try {
                const response = await axios.get(
                    route("backend.ai_policy_tracker.search"),
                    {
                        params: { name },
                    }
                );
                const result = response.data;
                setTableData(result);
            } catch (error) {
                console.error("Error fetching data:", error);
            } finally {
                setLoading(false);
            }
        }, 500);

        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    });

    const hasData = Array.isArray(tableData.data) && tableData.data.length > 0;
    const noTableDataTitle = "There are no (AI) policy tracker lists";

    return (
        <Layout>
            <Head title="Country Lists" />
            <div className="rounded-lg bg-white py-2 px-5">
                <div className="relative overflow-x-auto mt-5 min-h-[400px]">
                    <div className="mb-3 flex justify-between p-2">
                        <Input
                            placeholder="Search by name"
                            className="mb-2 text-sm font-normal text-light-blue"
                            onChange={(e) => searchHandle(e.target.value)}
                        />
                        <Button
                            onClick={toggleAddModal}
                            type="button"
                            className="text-sm h-fit text-gray-700 font-semibold flex gap-1 bg-secondary px-5 py-2 hover:bg-blue-100 items-center"
                        >
                            <AddIcon /> <span>Add (AI) Policy Tracker</span>
                        </Button>
                    </div>

                    {/* Loader */}
                    {loading ? (
                        <div className="flex justify-center items-center my-4 h-[400px] w-full">
                            <div className="loader border-t-4 border-blue-500 rounded-full w-8 h-8 animate-spin"></div>
                        </div>
                    ) : hasData ? (
                        <>
                            <table className="w-full text-sm text-left rtl:text-right text-gray-500">
                                <thead className="text-xs text-primary uppercase bg-secondary">
                                    <tr>
                                        <th
                                            style={{ width: "10%" }}
                                            className="px-6 py-3"
                                        >
                                            S.N
                                        </th>
                                        <th className="px-6 py-3">Name</th>
                                        <th className="px-6 py-3">
                                            Governing Body
                                        </th>
                                        <th className="px-6 py-3">
                                            Country Name
                                        </th>
                                        <th className="px-6 py-3">
                                            Status Name
                                        </th>
                                        <th className="px-6 py-3">
                                            total bookmark
                                        </th>
                                        <th className="px-6 py-3">
                                            Created At
                                        </th>
                                        <th
                                            style={{ width: "10%" }}
                                            className="px-6 py-3"
                                        >
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tableData.data.map((list, index) => (
                                        <tr
                                            className="bg-white border-b"
                                            key={list.id}
                                        >
                                            <td className="px-6 py-4">
                                                {tableData.from + index}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.ai_policy_name}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.governing_body}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.country?.name ?? "N/A"}
                                            </td>

                                            <td className="px-6 py-4">
                                                {list.status?.name ?? "N/A"}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.bookmarks_count ?? 0}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.formatted_created_at}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex gap-2">
                                                    {/* View Action */}
                                                    {/* <Link
                                                        href="#"
                                                        className="underline text-blue-950"
                                                    >
                                                        <ViewIcon />
                                                    </Link> */}

                                                    {/* Edit Action */}
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleEdiModal(
                                                                list.id
                                                            )
                                                        }
                                                    >
                                                        <EditIcon />
                                                    </Button>

                                                    {/* Delete Action */}
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleDeleteModal(
                                                                list.id
                                                            )
                                                        }
                                                    >
                                                        <DeleteIcon />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {tableData.total > 10 && (
                                <Pagination paginator={tableData} />
                            )}
                        </>
                    ) : (
                        <NoTableData noTableDataTitle={noTableDataTitle} />
                    )}
                </div>
            </div>

            {/* ******************************* Add Model *****************************/}
            <Model
                isOpen={isAddModalOpen}
                onClose={toggleAddModal}
                title="Add new (AI) Policy Tracker"
                width="max-w-6xl"
            >
                <Add
                    countries={countries}
                    status={status}
                    govAiIndex={govAiIndex}
                    onClose={toggleAddModal}
                />
            </Model>

            {/* ******************************* Edit Model *****************************/}
            <Model
                isOpen={isEditModalOpen}
                onClose={openEditModal}
                title="Edit (AI) Policy Tracker"
                width="max-w-6xl"
            >
                <Edit
                    countries={countries}
                    status={status}
                    onClose={openEditModal}
                    aiId={selectedAiId}
                    updatedData={updatingData}
                    govAiIndex={govAiIndex}
                />
            </Model>

            {/* ******************************* Delete Model *****************************/}
            <DeleteModel
                title={
                    "Are you sure you want to delete this (AI) policy tracker?"
                }
                routePath={"/backend/aipolicytracker/delete/"}
                isOpen={isDeleteModalOpen}
                onClose={() => toggleDeleteModal()}
                aiId={selectedAiId}
            />
        </Layout>
    );
}
