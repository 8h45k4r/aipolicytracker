import React, { useRef, useState } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import Layout from "@/Layouts/Backend/Layout";
import Button from "@/Components/Button";
import Navbar from "./Components/Navbar";
import AiPolicyTrackerProject from "./Components/AiPolicyTrackerProject";
import Contributor from "./Components/Contributor";
import Input from "@/Components/Input";
import { useEffect } from "react";

export default function HeaderMenu({ logo }) {
    const fileInputRefs = useRef([]);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [logoFile, setLogoFile] = useState(null); // Separate state for main logo
    const [navBarList, setNavBarList] = useState([{ name: "", url: "" }]);
    const [aiPolicyTracker, setAiPolicyTracker] = useState([
        { name: "", description: "" },
    ]);
    const [organizationList, setOrganizationList] = useState([
        { orgLogo: "", url: "" },
    ]);
    const [contributor, setContributor] = useState([
        { name: "", url: "", social: "" },
    ]);

    const { post, setData, errors } = useForm({
        logo: null,
        navBars: navBarList,
        aiPolicyTrackeData: aiPolicyTracker,
        contributorData: contributor,
        organizationData: organizationList,
    });

    const handleIconClick = (index) => {
        fileInputRefs.current[index].click();
    };

    const handleFileChange = (index, event) => {
        const file = event.target.files[0];
        const updatedList = [...selectedFiles];
        updatedList[index] = file;
        setSelectedFiles(updatedList);

        const updatedOrganizations = [...organizationList];
        updatedOrganizations[index].orgLogo = file;
        setOrganizationList(updatedOrganizations);

        // Update form data
        setData("organizationData", updatedOrganizations);
    };

    const handleLogoFileChange = (event) => {
        const file = event.target.files[0];
        setLogoFile(file);
        setData("logo", file);
    };

    const handleAddInputOrganization = () => {
        setOrganizationList([...organizationList, { orgLogo: "", url: "" }]);
        setSelectedFiles([...selectedFiles, null]); // Ensure selectedFiles matches organizationList length
    };

    const handleRemoveInputOrganization = (index) => {
        setOrganizationList(organizationList.filter((_, i) => i !== index));
        setSelectedFiles(selectedFiles.filter((_, i) => i !== index));
    };

    const handleInputChange = (index, field, value) => {
        const updatedList = [...organizationList];
        updatedList[index][field] = value;
        setOrganizationList(updatedList);
        setData("organizationData", updatedList);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const formData = new FormData();
        if (logoFile) formData.append("logo", logoFile);
        selectedFiles.forEach((file, index) => {
            if (file) formData.append(`orgLogos[${index}]`, file);
        });
        formData.append("navBars", JSON.stringify(navBarList));
        formData.append("aiPolicyTrackeData", JSON.stringify(aiPolicyTracker));
        formData.append("contributorData", JSON.stringify(contributor));
        formData.append("organizationData", JSON.stringify(organizationList));

        post("/backend/header-menu", formData, {
            headers: { "Content-Type": "multipart/form-data" },
            onError: (errors) => {
                console.error("Form submission failed", errors);
            },
        });
    };

    const [hasShadow, setHasShadow] = useState(false);

    useEffect(() => {
        // Check if the URL contains '#contributing'
        if (window.location.hash === "#contributing") {
            // Set the shadow for the element
            setHasShadow(true);

            // Remove shadow after 3 seconds
            const timer = setTimeout(() => {
                setHasShadow(false);
            }, 3000);

            // Clean up the timer on component unmount
            return () => clearTimeout(timer);
        }
    }, []);

    return (
        <Layout>
            <Head title="CMS" />
            <div className="">
                <form onSubmit={handleSubmit}>
                    {/* ////////////// */}

                    {/* Applicaiton Logo */}
                    <div class="block rounded-lg bg-white shadow-secondary-1 dark:bg-surface-dark dark:text-white text-surface">
                        <h5 class="border-b-2 border-neutral-100 px-6 py-3 text-lg font-medium leading-tight dark:border-white/10">
                            Application Setting
                        </h5>
                        <div class="p-6">
                            <p className="mb-3">Application Logo</p>
                            <div
                                className="w-44 h-32 border bg-secondary hover:text-blue-300 rounded-lg border-blue-200 cursor-pointer"
                                onClick={() => fileInputRefs.current[0].click()}
                            >
                                {logoFile || logo?.file_path ? (
                                    <img
                                        src={
                                            logoFile
                                                ? URL.createObjectURL(logoFile)
                                                : `/storage/${logo.file_path}`
                                        }
                                        alt="Selected Logo"
                                        className="w-full h-full rounded-lg"
                                    />
                                ) : (
                                    <div className="flex justify-center items-center w-full h-full">
                                        <div>
                                            <p className="text-center">
                                                Choose Logo
                                            </p>
                                            <div className="flex justify-center mt-2">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    xmlns:xlink="http://www.w3.org/1999/xlink"
                                                    fill="#000000"
                                                    height="50px"
                                                    width="50px"
                                                    version="1.1"
                                                    id="Capa_1"
                                                    viewBox="0 0 374.116 374.116"
                                                    xml:space="preserve"
                                                >
                                                    <g>
                                                        <path d="M344.058,207.506c-16.568,0-30,13.432-30,30v76.609h-254v-76.609c0-16.568-13.432-30-30-30c-16.568,0-30,13.432-30,30   v106.609c0,16.568,13.432,30,30,30h314c16.568,0,30-13.432,30-30V237.506C374.058,220.938,360.626,207.506,344.058,207.506z"></path>
                                                        <path d="M123.57,135.915l33.488-33.488v111.775c0,16.568,13.432,30,30,30c16.568,0,30-13.432,30-30V102.426l33.488,33.488   c5.857,5.858,13.535,8.787,21.213,8.787c7.678,0,15.355-2.929,21.213-8.787c11.716-11.716,11.716-30.71,0-42.426L208.271,8.788   c-11.715-11.717-30.711-11.717-42.426,0L81.144,93.489c-11.716,11.716-11.716,30.71,0,42.426   C92.859,147.631,111.855,147.631,123.57,135.915z"></path>
                                                    </g>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                            <input
                                type="file"
                                ref={(el) => (fileInputRefs.current[0] = el)}
                                style={{ display: "none" }}
                                onChange={handleLogoFileChange}
                            />
                        </div>
                    </div>

                    {/* Navigation section */}
                    {/* <div
                        class="block rounded-lg bg-white shadow-secondary-1 dark:bg-surface-dark dark:text-white text-surface mt-5">
                        <h5
                            class="border-b-2 border-neutral-100 px-6 py-3 text-lg font-medium leading-tight dark:border-white/10">
                            Naviagation
                        </h5>
                        <div class="p-6">
                            <Navbar
                                navBarList={navBarList}
                                handleInputChange={(index, field, value) => {
                                    const updatedList = [...navBarList];
                                    updatedList[index][field] = value;
                                    setNavBarList(updatedList);
                                    setData("navBars", updatedList);
                                }}
                                handleRemoveInput={(index) => {
                                    const updatedList = navBarList.filter(
                                        (_, i) => i !== index
                                    );
                                    setNavBarList(updatedList);
                                    setData("navBars", updatedList);
                                }}
                                handleAddInput={() => {
                                    const updatedList = [
                                        ...navBarList,
                                        { name: "", url: "" },
                                    ];
                                    setNavBarList(updatedList);
                                    setData("navBars", updatedList);
                                }}
                            />
                        </div>
                    </div> */}

                    {/* AIPolicyTracker Project */}
                    {/* <div class="block rounded-lg bg-white shadow-secondary-1 dark:bg-surface-dark dark:text-white text-surface mt-5">
                        <h5 class="border-b-2 border-neutral-100 px-6 py-3 text-lg font-medium leading-tight dark:border-white/10">
                            AIPolicyTracker Project Description
                        </h5>
                        <div class="p-6">
                            <AiPolicyTrackerProject
                                aiPolicyTracker={aiPolicyTracker}
                                handleAiPolicyTrackerChange={(
                                    index,
                                    field,
                                    value
                                ) => {
                                    const updatedList = [...aiPolicyTracker];
                                    updatedList[index][field] = value;
                                    setAiPolicyTracker(updatedList);
                                    setData("aiPolicyTrackeData", updatedList);
                                }}
                                handleRemoveAiPolicyTracker={(index) => {
                                    const updatedList = aiPolicyTracker.filter(
                                        (_, i) => i !== index
                                    );
                                    setAiPolicyTracker(updatedList);
                                    setData("aiPolicyTrackeData", updatedList);
                                }}
                                handleAddAiPolicyTracker={() => {
                                    const updatedList = [
                                        ...aiPolicyTracker,
                                        { name: "", description: "" },
                                    ];
                                    setAiPolicyTracker(updatedList);
                                    setData("aiPolicyTrackeData", updatedList);
                                }}
                            />
                        </div>
                    </div> */}

                    {/* Contributor */}
                    {/* <div class="block rounded-lg bg-white shadow-secondary-1 dark:bg-surface-dark dark:text-white text-surface mt-5">
                        <h5 class="border-b-2 border-neutral-100 px-6 py-3 text-lg font-medium leading-tight dark:border-white/10">
                            Contributor
                        </h5>
                        <div class="p-6">
                            <Contributor
                                contributor={contributor}
                                handleContributorChange={(
                                    index,
                                    field,
                                    value
                                ) => {
                                    const updatedList = [...contributor];
                                    updatedList[index][field] = value;
                                    setContributor(updatedList);
                                    setData("contributorData", updatedList);
                                }}
                                handleRemoveContributor={(index) => {
                                    const updatedList = contributor.filter(
                                        (_, i) => i !== index
                                    );
                                    setContributor(updatedList);
                                    setData("contributorData", updatedList);
                                }}
                                handleAddContributor={() => {
                                    const updatedList = [
                                        ...contributor,
                                        { name: "", url: "", social: "" },
                                    ];
                                    setContributor(updatedList);
                                    setData("contributorData", updatedList);
                                }}
                            />
                        </div>
                    </div> */}

                    {/* Contributing Organizations */}
                    <div
                        class={`block rounded-lg bg-white shadow-secondary-1 dark:bg-surface-dark dark:text-white text-surface mt-5 ${hasShadow == true ? 'shadow-md border border-blue-500' : ''}`}
                        id="contributing"
                    >
                        <div className="border-b-2 flex justify-between align-items-center py-2">
                            <h5 class="border-neutral-100 px-6 py-3 text-lg font-medium leading-tight dark:border-white/10">
                                Contributing Organizations
                            </h5>
                            {/* <Link className="text-sm border px-4 py-2 mr-5">
                                View Lists
                            </Link> */}

                            <div className="flex-none mr-4">
                                <Link
                                    href={route(
                                        "backend.header_menu.showContributingOrgIndex"
                                    )}
                                    className="text-primary-light h-fit bg-secondary hover:bg-blue-100 focus:ring-0 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center gap-2"
                                >
                                    {/* <i
                                        className={`fa ${
                                            authUserBookmarkCount
                                                ? "fa-star"
                                                : "fa-regular fa-star"
                                        } mr-3`}
                                    ></i> */}
                                    <span>View Lists</span>
                                </Link>
                            </div>
                        </div>
                        {/* <div className="border-b-2"></div> */}
                        <div class="p-6">
                            <div>
                                <p className="">Contributing Organizations</p>
                                {organizationList.map((_, index) => (
                                    <div
                                        key={index}
                                        className="border px-3 mb-3 pt-3 rounded-sm relative mt-6"
                                    >
                                        <button
                                            type="button"
                                            className="text-red-500 absolute right-[-8px] top-[-30px] hover:text-red-700"
                                            onClick={() =>
                                                handleRemoveInputOrganization(
                                                    index
                                                )
                                            }
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
                                        <div
                                            className="flex items-center gap-2 pb-3"
                                            key={index}
                                        >
                                            <div
                                                className="w-44 h-32 border bg-secondary hover:text-blue-300 rounded border-blue-200 cursor-pointer"
                                                onClick={() =>
                                                    handleIconClick(index + 1)
                                                }
                                            >
                                                {selectedFiles[index] ? (
                                                    <img
                                                        src={URL.createObjectURL(
                                                            selectedFiles[index]
                                                        )}
                                                        alt="Selected"
                                                        className="w-full h-full rounded"
                                                    />
                                                ) : (
                                                    <div className="flex justify-center items-center w-full h-full">
                                                        <div>
                                                            <p className="text-center">
                                                                Choose Logo
                                                            </p>
                                                            <div className="flex justify-center mt-2">
                                                                <svg
                                                                    xmlns="http://www.w3.org/2000/svg"
                                                                    xmlns:xlink="http://www.w3.org/1999/xlink"
                                                                    fill="#000000"
                                                                    height="50px"
                                                                    width="50px"
                                                                    version="1.1"
                                                                    id="Capa_1"
                                                                    viewBox="0 0 374.116 374.116"
                                                                    xml:space="preserve"
                                                                >
                                                                    <g>
                                                                        <path d="M344.058,207.506c-16.568,0-30,13.432-30,30v76.609h-254v-76.609c0-16.568-13.432-30-30-30c-16.568,0-30,13.432-30,30   v106.609c0,16.568,13.432,30,30,30h314c16.568,0,30-13.432,30-30V237.506C374.058,220.938,360.626,207.506,344.058,207.506z"></path>
                                                                        <path d="M123.57,135.915l33.488-33.488v111.775c0,16.568,13.432,30,30,30c16.568,0,30-13.432,30-30V102.426l33.488,33.488   c5.857,5.858,13.535,8.787,21.213,8.787c7.678,0,15.355-2.929,21.213-8.787c11.716-11.716,11.716-30.71,0-42.426L208.271,8.788   c-11.715-11.717-30.711-11.717-42.426,0L81.144,93.489c-11.716,11.716-11.716,30.71,0,42.426   C92.859,147.631,111.855,147.631,123.57,135.915z"></path>
                                                                    </g>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                            <input
                                                type="file"
                                                ref={(el) =>
                                                    (fileInputRefs.current[
                                                        index + 1
                                                    ] = el)
                                                }
                                                style={{ display: "none" }}
                                                onChange={(e) =>
                                                    handleFileChange(index, e)
                                                }
                                            />
                                            <Input
                                                value={
                                                    organizationList[index].url
                                                }
                                                onChange={(e) =>
                                                    handleInputChange(
                                                        index,
                                                        "url",
                                                        e.target.value
                                                    )
                                                }
                                                label="Website Url:"
                                                className="w-full"
                                                placeholder="Eg. https://example.org/"
                                            />
                                            {/* <button
                                        type="button"
                                        onClick={() =>
                                            handleRemoveInputOrganization(index)
                                        }
                                    >
                                        Remove
                                    </button> */}
                                        </div>

                                        {errors[
                                            `organizationData.${index}.orgLogo`
                                        ] && (
                                            <p className="text-red-500 text-sm">
                                                {
                                                    errors[
                                                        `organizationData.${index}.orgLogo`
                                                    ]
                                                }
                                            </p>
                                        )}

                                        {errors[
                                            `organizationData.${index}.url`
                                        ] && (
                                            <p className="text-red-500 text-sm">
                                                {
                                                    errors[
                                                        `organizationData.${index}.url`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                ))}
                                {/* <Button
                            type="button"
                            onClick={handleAddInputOrganization}
                        >
                            Add Organization
                        </Button> */}

                                <Button
                                    className="mt-4"
                                    type="button"
                                    onClick={handleAddInputOrganization}
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
                        </div>
                    </div>

                    {/* Save  */}
                    <div className="flex justify-end mt-4">
                        <Button
                            type="submit"
                            className="bg-[#4E87D3] hover:bg-[#3A6FA1] text-white px-5 py-2 border border-[#4E87D3]"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
