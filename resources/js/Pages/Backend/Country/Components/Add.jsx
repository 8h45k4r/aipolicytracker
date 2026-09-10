import Button from "@/Components/Button";
import Input from "@/Components/Input";
import { Head, useForm } from "@inertiajs/react";
import React, { useState } from "react";
import { ToggleSwitch } from "flowbite-react";
import { useEffect } from "react";

export default function Add() {
    // toggle switch for active and inactive
    const [isActive, setIsActive] = useState(true);

    const country = useForm({
        name: "",
        symbol: "",
        status: isActive,
    });

    const handleChange = (e) => {
        const { name, value } = e.target;
        country.setData(name, value);
    };

    useEffect(() => {
        country.setData("status", isActive);
    }, [isActive]);

    const formSubmit = (e) => {
        e.preventDefault();

        country.post(route("backend.country.store"));
    };

    return (
        <div>
            <Head title="Add Country" />
            <form onSubmit={formSubmit}>
                <div className="w-full">
                    <Input
                        name="name"
                        value={country.data.name}
                        htmlFor="name"
                        label="Country Name"
                        type="text"
                        onChange={handleChange}
                        placeholder="Eg. Nepal"
                        errorMsg={country.errors.name}
                    />
                </div>

                <div className="w-full mt-2">
                    <Input
                        name="symbol"
                        value={country.data.symbol}
                        htmlFor="name"
                        label="Symbol"
                        type="text"
                        onChange={handleChange}
                        placeholder="Eg. NP"
                        errorMsg={country.errors.symbol}
                    />
                </div>

                <div className="flex max-w-md flex-col items-start gap-4 mt-4">
                    <ToggleSwitch
                        checked={isActive}
                        label="Set Status"
                        onChange={setIsActive}
                    />
                </div>

                <div className=" float-end mt-5">
                    <Button
                        type="submit"
                        disabled={country.processing}
                        className="text-sm text-gray-700 font-semibold bg-secondary px-5 py-2 hover:bg-blue-100"
                    >
                        Add
                    </Button>
                </div>
            </form>
        </div>
    );
}
