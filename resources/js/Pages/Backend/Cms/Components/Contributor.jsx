import Button from "@/Components/Button";
import Input from "@/Components/Input";
import React from "react";

export default function Contributor({
    contributor,
    handleContributorChange,
    handleAddContributor,
    handleRemovecontributor,
}) {
    const handleSocialChange = (index, value) => {
        handleContributorChange(index, "social", value);
    };

    return (
        <div className="mt-3">
            <p>Contributors</p>
            {contributor.map((item, index) => (
                <div
                    className="border px-3 mb-3 pt-3 rounded-sm relative mt-6"
                    key={index}
                >
                    <button
                        type="button"
                        className="text-red-500 absolute right-[-8px] top-[-30px] hover:text-red-700"
                        onClick={() => handleRemovecontributor(index)}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                            className="size-5 hover:size-6"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                            />
                        </svg>
                    </button>
                    <div className=" grid grid-cols-3 gap-2 pb-3" key={index}>
                        <Input
                            placeholder="Eg. Pilot"
                            type="text"
                            name={`contributor_name_${index}`}
                            value={item.name}
                            onChange={(e) =>
                                handleContributorChange(
                                    index,
                                    "name",
                                    e.target.value
                                )
                            }
                        />
                        <Input
                            type="text"
                            name={`contributor_url_${index}`}
                            placeholder="Kink"
                            value={item.url} // Corrected from `description` to `url`
                            onChange={(e) =>
                                handleContributorChange(
                                    index,
                                    "url",
                                    e.target.value
                                )
                            }
                        />
                        {/* <select
                            name="social"
                            value={item.social || ""}
                            onChange={(e) =>
                                handleSocialChange(index, e.target.value)
                            }
                            className=""
                        >
                            <option value="">Choose Social Icon</option>
                            <option value="twitter">Twitter</option>
                            <option value="facebook">Facebook</option>
                            <option value="linkedin">Linkedin</option>
                        </select> */}

                        <select
                            name="social"
                            value={item.social || ""}
                            onChange={(e) =>
                                handleSocialChange(index, e.target.value)
                            }
                            id="small-input"
                            className="block w-full py-0 text-gray-900 border border-gray-300 rounded-lg bg-gray-50 text-xs focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Choose Social Icon</option>
                            <option value="twitter">Twitter</option>
                            <option value="facebook">Facebook</option>
                            <option value="linkedin">Linkedin</option>
                        </select>
                    </div>
                </div>
            ))}

            <Button
                className="mt-4"
                type="button"
                onClick={handleAddContributor}
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="#4E87D3"
                    viewBox="0 0 24 24"
                    strokeWidth="1.5"
                    stroke="currentColor"
                    className="size-6"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                    />
                </svg>
            </Button>
        </div>
    );
}
