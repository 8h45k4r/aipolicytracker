import React from "react";
import { useState } from "react";

export default function EditColumn({
    btnName = "Edit Columns",
    EditColumnLists = [],
    onCheckboxChange,
}) {
    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [checkedItems, setCheckedItems] = useState([]);

    const toggleDropdown = () => {
        setIsDropdownOpen(!isDropdownOpen);
    };

    const handleCheckboxChange = (event) => {
        const { value, checked } = event.target;
        let updatedCheckedItems = [...checkedItems];

        if (checked) {
            updatedCheckedItems.push(value);
        } else {
            updatedCheckedItems = updatedCheckedItems.filter(
                (item) => item !== value
            );
        }

        setCheckedItems(updatedCheckedItems);
        onCheckboxChange(updatedCheckedItems);
    };
    return (
        <div>
            <button
                id="dropdownDefaultButton"
                onClick={toggleDropdown}
                // data-dropdown-toggle="dropdown"
                className="text-primary-light bg-secondary hover:bg-blue-100 focus:ring-0 focus:outline-none font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center"
                type="button"
            >
                {btnName}
                <svg
                    className="w-2.5 h-2.5 ms-3"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 10 6"
                >
                    <path
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2"
                        d="m1 1 4 4 4-4"
                    />
                </svg>
            </button>

            {isDropdownOpen && (
                <div
                    // id="dropdown"
                    className="z-10 absolute bg-white divide-y divide-gray-100 rounded-lg shadow w-44 mt-2 ml-[-14px]"
                >
                    <ul
                        className="py-2 text-sm text-primary"
                        aria-labelledby="dropdownDefaultButton"
                    >
                        {EditColumnLists.map((item, index) => (
                            <li key={index}>
                                <div className="flex gap-2 items-center px-4 py-2 hover:bg-gray-100 font-bold">
                                    <input
                                        type="checkbox"
                                        id={item.name}
                                        // className="rounded focus:ring-0"
                                        value={item.title}
                                        onChange={handleCheckboxChange}
                                        checked={item.status ? true : checkedItems.includes(item.title)}

                                        className={`rounded focus:ring-0 ${
                                            item.status
                                                ? "disabled-checkbox"
                                                : ""
                                        }`}
                                        // checked={item.status ? true : undefined}
                                        disabled={item.status}
                                    />
                                    <label
                                        htmlFor={item.name}
                                        className="capitalize"
                                    >
                                        {item.title}
                                    </label>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
