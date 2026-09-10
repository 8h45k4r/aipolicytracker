import React from "react";
import { Link, usePage } from "@inertiajs/react";
import NoTableData from "./NoTableData";

import { limitWords } from "@/utils/limitWords";

export default function Table({
    tableData = [],
    noTableDataTitle = "No Data",
    checkedColWithData,
    govAiIndex = [],
}) {
    const hasData = Array.isArray(tableData) && tableData.length > 0;

    const authcheck = usePage();

    const isAuth = authcheck.props.auth.user
        ? authcheck.props.auth.user?.id
        : null;

    const isVerifiedEmail =
        authcheck.props.auth.user?.email_verified_at == null ? false : true;

    return (
        <div className="relative overflow-x-auto mx-5 mt-5 hidden md:block">
            <table className="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead className="text-xs text-primary uppercase bg-secondary">
                    <tr>
                        <th
                            scope="col"
                            className="px-6 py-3 sticky-column border-e border-neutral-200 "
                        >
                        Name
                        </th>
                        <th scope="col" className="px-6 py-3">
                            Country / Region
                        </th>
                        <th scope="col" className="px-6 py-3">
                            Governing Body
                        </th>
                        <th scope="col" className="px-6 py-3">
                            Announcement
                        </th>
                        <th scope="col" className="px-6 py-3">
                            Status
                        </th>
                        {/* <th scope="col" className="px-6 py-3">
                            Document Link
                        </th> */}
                        <th scope="col" className="px-6 py-3">
                            Technology partners
                        </th>
                        {/* after show when checked editColumn */}
                        {checkedColWithData.includes(
                            "GovAi Index"
                        ) && (
                            <th scope="col" className="px-6 py-3">
                               GovAi Index
                            </th>
                        )}
                        {checkedColWithData.includes("Document Link") && (
                            <th scope="col" className="px-6 py-3">
                                Document Link
                            </th>
                        )}

                        {checkedColWithData.includes(
                            "Governance structure"
                        ) && (
                            <th scope="col" className="px-6 py-3">
                                Governance structure
                            </th>
                        )}

                        {checkedColWithData.includes(
                            "Main motivation/goals of the AI policy"
                        ) && (
                            <th scope="col" className="px-6 py-3">
                                Main motivation/goals of the AI policy
                            </th>
                        )}

                        {checkedColWithData.includes("Description") && (
                            <th scope="col" className="px-6 py-3">
                                description
                            </th>
                        )}

                        {checkedColWithData.includes(
                            "Link to announcement"
                        ) && (
                            <th scope="col" className="px-6 py-3">
                                Link to announcement
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {tableData.map((list) => (
                        <tr className="bg-white border-b" key={list.id}>
                            <td
                                width="25%"
                                className="px-6 py-4 sticky-column-td border-e border-neutral-200 "
                            >
                                <div className="flex gap-x-2 items-center">
                                    <Link
                                        href={route("frontend.watch_list.add", {
                                            id: list.id,
                                            isBooked: list.bookmark
                                                ?.ai_policy_tracker_id
                                                ? true
                                                : false,
                                        })}
                                        className="text-primary-light"
                                    >
                                        {list.bookmark?.ai_policy_tracker_id ? (
                                            <i className="fa fa-star A"></i>
                                        ) : (
                                            <i className="fa-regular fa-star B"></i>
                                        )}
                                    </Link>

                                    <Link
                                        href={route(
                                            "frontend.single_ai_policy_tracker.index",
                                            { id: list.id }
                                        )}
                                        className="text-primary hover:underline"
                                    >
                                        {limitWords(list.ai_policy_name, 4)}
                                    </Link>
                                </div>
                            </td>
                            <td width="15%" className="px-6 py-4">
                                {list.country?.name}
                            </td>
                            <td width="25%" className="px-6 py-4">
                                {list.governing_body}
                            </td>
                            <td width="10%" className="px-6 py-4">
                                {list.formatted_created_at}
                            </td>
                            <td width="15%" className="px-6 py-4">
                                <span
                                    className={`status-state ${
                                        list.status?.name || ""
                                    }`}
                                ></span>
                                {list.status?.name}
                            </td>

                            <td width="10%" className="px-6 py-4">
                                {list.technology_partners}
                            </td>

                            {/* after checked editColumn */}

                            {checkedColWithData.includes(
                                "GovAi Index"
                            ) && (
                                <td width="10%" className="px-6 py-4">
                                    {limitWords(list.gov_ai_index, 3)}
                                </td>
                            )}
                            {checkedColWithData.includes("Document Link") && (
                                <td width="10%" className="px-6 py-4">
                                    {isAuth ? (
                                        isVerifiedEmail ? (
                                            <a
                                                href={
                                                    list.whitepaper_document_link
                                                }
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="underline text-blue-950"
                                            >
                                                open
                                            </a>
                                        ) : (
                                            <Link
                                                href={route(
                                                    "verification.notice"
                                                )}
                                            >
                                                open
                                            </Link>
                                        )
                                    ) : (
                                        <Link href={route("register")}>
                                            open
                                        </Link>
                                    )}
                                </td>
                            )}
                            {checkedColWithData.includes(
                                "Governance structure"
                            ) && (
                                <td width="10%" className="px-6 py-4">
                                    {limitWords(list.governance_structure, 3)}
                                </td>
                            )}
                            {checkedColWithData.includes(
                                "Main motivation/goals of the AI policy"
                            ) && (
                                <td width="10%" className="px-6 py-4">
                                    {limitWords(list.main_motivation, 3)}
                                </td>
                            )}
                            {checkedColWithData.includes("Description") && (
                                <td width="10%" className="px-6 py-4">
                                    {limitWords(list.description, 3)}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
            {!hasData && <NoTableData noTableDataTitle={noTableDataTitle} />}
        </div>
    );
}
