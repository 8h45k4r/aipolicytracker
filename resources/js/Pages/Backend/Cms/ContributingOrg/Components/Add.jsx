import React from "react";
import Button from "@/Components/Button";
import Input from "@/Components/Input";
import Select from "@/Components/Select";
import { useForm } from "@inertiajs/react";
import { CKEditor } from "@ckeditor/ckeditor5-react";
import ClassicEditor from "@ckeditor/ckeditor5-build-classic";
import { useState } from "react";

export default function Add({
    countries = null,
    status = null,
    onClose,
    govAiIndex = [],
}) {
    const defaultDate = new Date().toISOString().substring(0, 10);

    const formAiPolicy = useForm({
        gov_ai_index: "policy",
        // gov_ai_index_name: "",
        ai_policy_name: "",
        country_id: "",
        governing_body: "",
        announcement_year: defaultDate,
        status_id: "",
        whitepaper_document_link: "",
        technology_partners: "",
        governance_structure: "",
        main_motivation: "",
        description: "",
    });

    // const [govAiIndexValue, setGovAiIndexValue] = useState("policy");

    const handleChange = (e) => {
        if (e && e.target) {
            // Handle regular inputs
            const { name, value } = e.target;
            formAiPolicy.setData(name, value);
        } else if (e && e.name && e.value) {
            // Handle react-select
            formAiPolicy.setData(e.name, e.value);
            // if (e.value == "strategy") {
            //     setGovAiIndexValue(e.value);
            // }

            // if (e.value == "policy") {
            //     setGovAiIndexValue(e.value);
            // }
        }
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        formAiPolicy.post(route("backend.ai_policy_tracker.store"), {
            onSuccess: () => {
                formAiPolicy.reset();
                onClose();
            },
        });
    };

    return (
        <div>
            <form onSubmit={handleSubmit}>
                <div className="grid gap-6 mb-6 md:grid-cols-4 ">
                    <Select
                        onChange={(option) =>
                            handleChange({
                                name: "gov_ai_index",
                                value: option.value ?? null,
                            })
                        }
                        name="gov_ai_index"
                        value={govAiIndex.find(
                            (govAiIndexName) =>
                                govAiIndexName.value ===
                                formAiPolicy.data.gov_ai_index
                        )}
                        label="GovAi Index"
                        options={[
                            { label: "Select any Gov", value: "0" },
                            ...govAiIndex,
                        ]}
                        errorMsg={formAiPolicy.errors.gov_ai_index}
                    />

                    {/* {govAiIndexValue == "policy" && ( */}
                    <Input
                        name="ai_policy_name"
                        value={formAiPolicy.data.ai_policy_name}
                        htmlFor="name"
                        label="Name"
                        type="text"
                        onChange={handleChange}
                        placeholder="Eg. Act of (AI) not allowed"
                        errorMsg={formAiPolicy.errors.ai_policy_name}
                    />
                    {/* )} */}
                    {/* {govAiIndexValue == "strategy" && (
                        <Input
                            name="gov_ai_index_name"
                            value={formAiPolicy.data.gov_ai_index_name}
                            htmlFor="gov_ai_index_name"
                            label="GovAi Index name"
                            type="text"
                            onChange={handleChange}
                            placeholder="Eg. govai index name"
                            errorMsg={formAiPolicy.errors.gov_ai_index_name}
                        />
                    )} */}

                    <Select
                        onChange={(option) =>
                            handleChange({
                                name: "country_id",
                                value: option.value ?? null,
                            })
                        }
                        name="country_id"
                        value={countries.find(
                            (country) =>
                                country.value === formAiPolicy.data.country_id
                        )}
                        label={"Country"}
                        options={[
                            { label: "Select any country", value: "0" },
                            ...countries,
                        ]}
                        errorMsg={formAiPolicy.errors.country_id}
                    />

                    <Input
                        onChange={handleChange}
                        name="governing_body"
                        value={formAiPolicy.data.governing_body}
                        htmlFor="governing_body"
                        label="Governing body"
                        type="text"
                        placeholder="Eg. State Council of China"
                    />
                    <Input
                        onChange={handleChange}
                        name="announcement_year"
                        value={formAiPolicy.data.announcement_year}
                        htmlFor="announcement_year"
                        label="Announcement year"
                        type="date"
                    />

                    <Select
                        onChange={(option) =>
                            handleChange({
                                name: "status_id",
                                value: option.value,
                            })
                        }
                        name="status_id"
                        value={status.find(
                            (statusOption) =>
                                statusOption.value ===
                                formAiPolicy.data.status_id
                        )}
                        label={"Status"}
                        options={[
                            { label: "Select any status", value: "0" },
                            ...status,
                        ]}
                        errorMsg={formAiPolicy.errors.status_id}
                    />

                    <Input
                        onChange={handleChange}
                        name="whitepaper_document_link"
                        value={formAiPolicy.data.whitepaper_document_link}
                        htmlFor="whitepaper_document_link"
                        label="Whitepaper / Document link"
                        type="text"
                        placeholder="Eg. www.document.com"
                    />
                    <Input
                        onChange={handleChange}
                        name="technology_partners"
                        value={formAiPolicy.data.technology_partners}
                        htmlFor="technology_partners"
                        label="Technology partners"
                        type="text"
                        placeholder="Eg. Ukt"
                    />
                    <Input
                        onChange={handleChange}
                        name="governance_structure"
                        value={formAiPolicy.data.governance_structure}
                        htmlFor="governance_structure"
                        label="Governance structure"
                        type="text"
                        placeholder="Nexus Governance System"
                    />

                    <Input
                        onChange={handleChange}
                        name="main_motivation"
                        value={formAiPolicy.data.main_motivation}
                        htmlFor="main_motivation"
                        label="Main motivation/goals of the AI policy"
                        type="text"
                        placeholder="Eg. InnoSafe AI Guidelines"
                    />
                </div>

                <div className="mb-6">
                    <CKEditor
                        editor={ClassicEditor}
                        data={formAiPolicy.data.description}
                        onChange={(event, editor) => {
                            const data = editor.getData();
                            handleChange({
                                target: {
                                    name: "description",
                                    value: data,
                                },
                            });
                        }}
                    />
                    {formAiPolicy.errors.description && (
                        <div className="text-red-600 text-sm mt-1">
                            {formAiPolicy.errors.description}
                        </div>
                    )}
                </div>

                <div className="float-end">
                    <Button
                        type="submit"
                        disabled={formAiPolicy.processing}
                        className="text-sm text-gray-700 font-semibold bg-secondary px-5 py-2 hover:bg-blue-100"
                    >
                        Add
                    </Button>
                </div>
            </form>
        </div>
    );
}
