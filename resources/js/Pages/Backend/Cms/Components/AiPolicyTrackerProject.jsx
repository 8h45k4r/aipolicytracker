// Cms/AiPolicyTracker.jsx
import React from "react";
import Input from "@/Components/Input";
import TextArea from "@/Components/TextArea";
import Button from "@/Components/Button";

const AiPolicyTrackerProject = ({ aiPolicyTracker, handleAiPolicyTrackerChange, handleAddAiPolicyTracker, handleRemoveAiPolicyTracker }) => {
    return (
        <div className="mt-3">
            <p className="text-primary-light">AIPolicyTracker Project</p>
            <h1>Description</h1>
            {aiPolicyTracker.map((item, index) => (
                <div
                    className="border px-3 mb-3 pt-3 rounded-sm relative mt-6"
                    key={index}
                >
                    <button
                        type="button"
                        className="text-red-500 absolute right-[-8px] top-[-30px] hover:text-red-700"
                        onClick={() => handleRemoveAiPolicyTracker(index)}
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

                    <Input
                        placeholder="Eg. Pilot"
                        type="text"
                        name={`ai_name_${index}`}
                        value={item.name}
                        onChange={(e) =>
                            handleAiPolicyTrackerChange(index, "name", e.target.value)
                        }
                    />
                    <TextArea
                        type="text"
                        name={`ai_description_${index}`}
                        placeholder="Eg. Countries piloting AI policy in a real environment with a limited number of parties."
                        value={item.description}
                        onChange={(e) =>
                            handleAiPolicyTrackerChange(index, "description", e.target.value)
                        }
                    />
                </div>
            ))}

            <Button
                className="mt-4"
                type="button"
                onClick={handleAddAiPolicyTracker}
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
};

export default AiPolicyTrackerProject;
