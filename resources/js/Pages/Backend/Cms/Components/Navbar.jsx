// Cms/Navbar.jsx
import React from "react";
import Input from "@/Components/Input";
import Button from "@/Components/Button";

const Navbar = ({ navBarList, handleInputChange, handleRemoveInput, handleAddInput }) => {
    return (
        <div className="mt-5">
            <p>Nav Bar</p>
            <div>
                {navBarList.map((item, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-3 gap-2 mb-3"
                    >
                        <Input
                            label="name"
                            type="text"
                            name={`navbar_name_${index}`}
                            placeholder="Eg. Home"
                            value={item.name}
                            onChange={(e) =>
                                handleInputChange(index, "name", e.target.value)
                            }
                        />
                        <Input
                            label="url"
                            type="text"
                            name={`navbar_url_${index}`}
                            placeholder="Eg. /home"
                            value={item.url}
                            onChange={(e) =>
                                handleInputChange(index, "url", e.target.value)
                            }
                        />
                        <button
                            type="button"
                            className="text-red-500 hover:text-red-700"
                            onClick={() => handleRemoveInput(index)}
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
                    </div>
                ))}
            </div>

            <Button
                className="mt-4"
                type="button"
                onClick={handleAddInput}
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

export default Navbar;
