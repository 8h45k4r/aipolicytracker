import React, { useState } from "react";
import { useDropzone } from "react-dropzone";

const ImageUpload = ({
    onImageChange,
    name,
    multiple = false,
    title,
    errorMsg = null,
}) => {
    const [images, setImages] = useState([]);
    const [previewUrls, setPreviewUrls] = useState([]);

    const { getRootProps, getInputProps } = useDropzone({
        accept: "image/*",
        multiple: multiple,
        onDrop: (acceptedFiles) => {
            const files = multiple ? acceptedFiles : [acceptedFiles[0]];
            setImages(files);
            setPreviewUrls(files.map((file) => URL.createObjectURL(file)));
            onImageChange(files);
        },
    });

    return (
        <div>
            <div {...getRootProps({ className: "dropzone w-fit" })}>
                <input {...getInputProps()} name={name} />
                <p>{title}</p>
            </div>
            {previewUrls.length > 0 && (
                <div className="image-previews">
                    {previewUrls.map((url, index) => (
                        <img key={index} src={url} alt={`Preview ${index}`} />
                    ))}
                </div>
            )}
            {errorMsg && (
                <p className=" text-red-500 text-xs mt-1">{errorMsg}</p>
            )}
            <style jsx>{`
                .dropzone {
                    border: 2px dashed #cccccc;
                    border-radius: 4px;
                    padding: 20px;
                    text-align: center;
                    cursor: pointer;
                    margin-bottom: 20px;
                }
                .image-previews {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 10px;
                }
                .image-previews img {
                    width: 100%;
                    height: auto;
                    border-radius: 4px;
                    object-fit: cover;
                }
            `}</style>
        </div>
    );
};

export default ImageUpload;
