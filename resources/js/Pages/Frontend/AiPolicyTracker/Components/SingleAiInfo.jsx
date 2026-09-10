import { Link } from "@inertiajs/react";
import React from "react";

export default function SingleAiInfo({ aiPolicyTrackerdetail }) {
    const {
        ai_policy_name,
        technology_partners,
        announcement_year,
        governing_body,
        governance_structure,
        status,
        country,
        description,
        whitepaper_document_link,
    } = aiPolicyTrackerdetail;

    return (
        <div className="bg-white rounded-md py-3">
            <div className="border-b pb-4 px-5">
                <div>
                    <p className="font-bold text-primary-light text-lg">
                        {ai_policy_name ?? ""}{" "}
                        <i className="fa-regular fa-star"></i>
                    </p>
                </div>
                <div className="flex gap-5 mt-3 text-sm">
                    <p className="text-sm text-primary">
                        {technology_partners ?? ""}
                    </p>
                </div>
            </div>

            <div>
                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Announcement Year</p>
                    <p className="text-gray-600">{announcement_year ?? ""}</p>
                </div>

                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Governing Body</p>
                    <p className="text-gray-600">{governing_body ?? ""}</p>
                </div>
                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Governance Structure</p>
                    <p className="text-gray-600">
                        {governance_structure ?? ""}
                    </p>
                </div>
                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Status</p>
                    <p className="text-gray-600">{status?.name ?? ""}</p>
                </div>
                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Country</p>
                    <p className="text-gray-600">{country?.name ?? ""}</p>
                </div>
                <div className="px-5 mt-4">
                    <p className="capitalize text-xs">Description</p>
                    <p
                        className="text-gray-600"
                        dangerouslySetInnerHTML={{
                            __html: description ?? "",
                        }}
                    ></p>
                </div>

                <div className="px-5 mt-4 border-t-2 pt-4">
                    <p className="text-xs">Link to announcement</p>
                    <a
                        href={whitepaper_document_link ?? "#"}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-gray-600"
                    >
                        <i className="fa-solid fa-link text-xs"></i>
                        <span className="ml-1 hover break-words underline text-blue-500 hover:text-blue-700">
                            {whitepaper_document_link ?? "No link available"}
                        </span>
                    </a>
                </div>
            </div>
        </div>
    );
}
