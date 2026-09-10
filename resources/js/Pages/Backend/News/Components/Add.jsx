import Button from "@/Components/Button";
import Input from "@/Components/Input";
import Select from "@/Components/Select";
import { Head, useForm } from "@inertiajs/react";
import React, { useState } from "react";
import ImageUpload from "@/Components/ImageUpload";
import { CKEditor } from "@ckeditor/ckeditor5-react";
import ClassicEditor from "@ckeditor/ckeditor5-build-classic";

export default function Add({
    countries = null,
    categories = null,
    aiPolicyTrackers = null,
    onClose,
}) {
    const defaultDate = new Date().toISOString().substring(0, 10);
    const [thumbnails, setThumbnails] = useState([]);
    const [featureImages, setFeatureImages] = useState([]);

    const formAiPolicy = useForm({
        title: "",
        status_id: "",
        policy_tracker_id: "",
        upload_date: defaultDate,
        description: "",
        // thumbnails: [],
        thumbnail: "",
        future_images: [],
        onClose,
    });

    const handleChange = (e) => {
        if (e && e.target) {
            const { name, value } = e.target;
            formAiPolicy.setData(name, value);
        } else if (e && e.name && e.value) {
            formAiPolicy.setData(e.name, e.value);
        }
    };

    const handleImageChange = (images, type) => {
        if (type === "thumbnail") {
            setThumbnails(images);
            formAiPolicy.setData("thumbnail", images[0]);
        }
        // else if (type === "feature_img") {
        //     setFeatureImages(images);
        //     formAiPolicy.setData("future_images", images);
        // }
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        // const formData = new FormData();
        // Object.keys(formAiPolicy.data).forEach((key) => {
        //     if (key === "thumbnails" || key === "future_images") {
        //         formAiPolicy.data[key].forEach((image, index) => {
        //             formData.append(`${key}[${index}]`, image);
        //         });
        //     } else {
        //         formData.append(key, formAiPolicy.data[key]);
        //     }
        // });

        formAiPolicy.post(route("backend.news.store"), {
            // data: formData,
            // headers: {
            //     "Content-Type": "multipart/form-data",
            // },
            onSuccess: () => {
                formAiPolicy.reset();
                onClose();
            },
        });
    };

    return (
        <div>
            <Head title="Add News" />
            <form onSubmit={handleSubmit}>
                <div className="w-full">
                    <Input
                        name="title"
                        value={formAiPolicy.data.title}
                        htmlFor="name"
                        label="News Title"
                        type="text"
                        onChange={handleChange}
                        placeholder="Eg. Global AI Law and Policy Tracker"
                        errorMsg={formAiPolicy.errors.title}
                    />
                </div>

                <div className="grid gap-6 md:grid-cols-2 mt-5">
                    <Select
                        onChange={(option) =>
                            handleChange({
                                name: "status_id",
                                value: option.value,
                            })
                        }
                        name="status_id"
                        value={categories.find(
                            (list) => list.value === formAiPolicy.data.status_id
                        )}
                        label="Category"
                        options={categories}
                        errorMsg={formAiPolicy.errors.status_id}
                    />

                    <Select
                        onChange={(option) =>
                            handleChange({
                                name: "policy_tracker_id",
                                value: option.value,
                            })
                        }
                        name="policy_tracker_id"
                        value={aiPolicyTrackers.find(
                            (list) =>
                                list.value ===
                                formAiPolicy.data.policy_tracker_id
                        )}
                        label="AI Policy Tracker"
                        options={aiPolicyTrackers}
                        errorMsg={formAiPolicy.errors.policy_tracker_id}
                    />

                    <Input
                        onChange={handleChange}
                        name="upload_date"
                        value={formAiPolicy.data.upload_date}
                        htmlFor="upload_date"
                        label="Created At"
                        type="date"
                    />
                </div>

                <div className="grid gap-6 md:grid-cols-2 mt-5">
                    <ImageUpload
                        onImageChange={(images) =>
                            handleImageChange(images, "thumbnail")
                        }
                        name="thumbnail"
                        title="Drag & drop thumbnail"
                        errorMsg={formAiPolicy.errors.thumbnail}
                    />

                    {/* <ImageUpload
                        multiple
                        onImageChange={(images) =>
                            handleImageChange(images, "feature_img")
                        }
                        name="feature_img"
                        title="Drag & drop multiple feature image"
                    /> */}
                </div>

                <div className="mt-5">
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
                        className="custom-ckeditor"
                    />
                    {formAiPolicy.errors.description && (
                        <div className="text-red-600 text-sm mt-1">
                            {formAiPolicy.errors.description}
                        </div>
                    )}
                </div>

                <div className=" float-end mt-5">
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
