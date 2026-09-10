import React, { useEffect, useState } from "react";
import AddIcon from "@/Components/AddIcon";
import Button from "@/Components/Button";
// import DeleteIcon from "@/Components/DeleteIcon";
// import EditIcon from "@/Components/EditIcon";
// import Model from "@/Components/Model";
// import NoTableData from "@/Components/Table/NoTableData";
// import ViewIcon from "@/Components/ViewIcon";
import Layout from "@/Layouts/Backend/Layout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import DeleteIcon from "@/Components/DeleteIcon";
import ViewIcon from "@/Components/ViewIcon";
import NoTableData from "@/Components/Table/NoTableData";
import Pagination from "@/Components/Pagination";
import Model from "@/Components/Model";
// import View from "./Components/View";
import View from "./Components/View";
// import Pagination from "@/Components/Pagination";
// import DeleteModel from "@/Components/DeleteModel";
// import Edit from "./Components/Edit";
import axios from "axios";

export default function Index({ tableData }) {
    const hasData = Array.isArray(tableData.data) && tableData.data.length > 0;
    const noTableDataTitle = "There are no news lists";

    // add modal
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    // add toggle func
    const toggleAddModal = () => setIsAddModalOpen(!isAddModalOpen);

    // set updating data
    const [updatingData, setUpdatingData] = useState(null);

    const toggleEdiModal = async (id = null) => {
        if (!id) return;

        try {
            // Make a GET request to fetch data by ID
            const response = await axios.post(`/backend/users/views/${id}`);

            const updatedData = response.data.user; // Adjust according to your response structure
            if (updatedData) {
                // Set the updating data and open the modal if data is not null
                setUpdatingData(updatedData);
                setIsAddModalOpen(!isAddModalOpen);

                toggleAddModal;
            } else {
                // Handle case where data is null, e.g., show a warning or notification
            }
        } catch (error) {
            // Handle error case if needed
            console.error("Failed to fetch data:", error);
        }
    };
    return (
        <Layout>
            <Head title="News Lists" />
            <div className="rounded-lg bg-white py-2 px-5">
                <div className="relative overflow-x-auto mt-5 min-h-[400px]">
                    {hasData ? (
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
                                        <th className="px-6 py-3">user name</th>
                                        <th
                                            className="px-6 py-3"
                                            style={{ width: "25%" }}
                                        >
                                            email
                                        </th>
                                        <th className="px-6 py-3">Phone No.</th>
                                        <th
                                            className="px-6 py-3"
                                            style={{ width: "13%" }}
                                        >
                                            created at
                                        </th>
                                        <th className="px-6 py-3">Action</th>
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
                                                {list.name}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.email ?? "N/A"}
                                                {/* <span className=" bg-green-500 text-white px-1 rounded-full text-xs">
                                            verified
                                        </span> */}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.user_info?.phone_no ??
                                                    "N/A"}
                                            </td>
                                            <td className="px-6 py-4">
                                                {list.formatted_created_at ??
                                                    "N/A"}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex gap-2">
                                                    {/* <Link
                                                        href={route(
                                                            "backend.users.view",
                                                            list.id
                                                        )}
                                                        className="underline text-blue-950"
                                                    >
                                                        <ViewIcon />
                                                    </Link> */}

                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleEdiModal(
                                                                list.id
                                                            )
                                                        }
                                                    >
                                                        <ViewIcon />
                                                    </Button>
                                                    {/* <Button
                                                type="button"
                                                onClick={() =>
                                                    toggleDeleteModal(list.id)
                                                }
                                            >
                                                <DeleteIcon />
                                            </Button> */}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {tableData.data.length >= 10 && (
                                <Pagination paginator={tableData} />
                            )}
                        </>
                    ) : (
                        <NoTableData noTableDataTitle={noTableDataTitle} />
                    )}
                </div>
            </div>

            {/* add model */}
            <Model
                isOpen={isAddModalOpen}
                onClose={toggleAddModal}
                title="User Information"
                width="max-w-xl"
            >
                {/* {updatingData} */}
                <View user={updatingData} />
            </Model>

            {/* edit model */}
            {/* <Model
                isOpen={isEditModalOpen}
                onClose={openEditModal}
                title="Edit News"
                width="max-w-6xl"
            >
                <Edit
                    aiPolicyTrackers={aiPolicyTrackers}
                    countries={countries}
                    categories={categories}
                    onClose={openEditModal}
                    aiId={selectedAiId}
                    updatedData={updatingData}
                />
            </Model> */}
            {/*
            <DeleteModel
                title={"Are you sure you want to delete this news?"}
                routePath={"/backend/news/delete/"}
                isOpen={isDeleteModalOpen}
                onClose={() => toggleDeleteModal()}
                aiId={selectedAiId}
            /> */}
        </Layout>
    );
}
