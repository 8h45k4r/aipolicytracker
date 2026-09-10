import React from "react";
import Box from "@mui/material/Box";
import { SimpleTreeView } from "@mui/x-tree-view/SimpleTreeView";
import { TreeItem } from "@mui/x-tree-view/TreeItem";
import Checkbox from "@/Components/Checkbox";
import { useForm } from "@inertiajs/react";

export default function PublicPurpose({ onFormUpdate }) {
    const answerOptions = [
        { id: "not_considering", label: "Not considering" },
        { id: "considering", label: "Considering" },
        { id: "developing", label: "Developing" },
        { id: "exists", label: "Exists" },
        { id: "in_operation", label: "In operation" },
    ];

    const { data, setData } = useForm({
        questions: [
            {
                id: "1.a",
                question:
                    "Has your government produced any guidance for public servants on trustworthy AI in the public sector?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },
            {
                id: "1.b",
                question:
                    "Are there specifc courses and training available for public servants on the trustworthy use of AI in government and public services?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },

            {
                id: "1.c",
                question:
                    "Are principles of trustworthy AI covered in any digital service design guidance for digital service teams?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },

            {
                id: "1.d",
                question:
                    "Does your government regularly inform and engage users when it carries out AI projects?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },

            {
                id: "1.e",
                question:
                    "Does your government routinely carry out public service assessments to understand how well services meet user needs and how well the service is being managed?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },

            {
                id: "1.f",
                question:
                    "Are there any government initiatives supporting private sector AI projects aimed at the public good?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },

            {
                id: "1.g",
                question:
                    "Does your government have any AI R&D initiatives with academia aimed at the public good?",
                public_purpose: {
                    not_considering: false,
                    considering: false,
                    developing: false,
                    exists: false,
                    in_operation: false,
                },
            },
        ],
    });

    const handleOnChangeCheckBox = (event, questionId) => {
        const { name, checked } = event.target;

        setData((prevData) => {
            const updatedQuestions = prevData.questions.map((q) =>
                q.id === questionId
                    ? {
                          ...q,
                          public_purpose: {
                              ...Object.fromEntries(
                                  Object.keys(q.public_purpose).map((key) => [
                                      key,
                                      false,
                                  ])
                              ),
                              [name]: checked, // Set only the selected answer to true
                          },
                      }
                    : q
            );
            // Call the callback with updated data
            onFormUpdate(updatedQuestions);

            return {
                ...prevData,
                questions: updatedQuestions,
            };
        });
    };

    return (
        <Box sx={{ minHeight: 352, minWidth: 250 }}>
            <SimpleTreeView defaultExpandedItems={["grid"]}>
                <TreeItem itemId="grid" label="Public Purpose">
                    {/* <div className="grid grid-cols-2"> */}
                    {data.questions.map((q) => (
                        <div key={q.id} className="px-5 mt-3 grid">
                            <h1>
                                <span className="mr-2">{q.id}. </span>
                                {q.question}
                            </h1>
                            <div className="grid grid-cols-2">
                                {answerOptions.map(({ id, label }) => (
                                    <div key={id} className="mt-3">
                                        <Checkbox
                                            id={`question_${q.id}_${id}`}
                                            className="focus:ring-0"
                                            name={id}
                                            checked={q.public_purpose[id]}
                                            onChange={(e) =>
                                                handleOnChangeCheckBox(e, q.id)
                                            }
                                        />
                                        <label
                                            htmlFor={`question_${q.id}_${id}`}
                                            className="ml-2"
                                        >
                                            {label}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </TreeItem>
            </SimpleTreeView>
        </Box>
    );
}
