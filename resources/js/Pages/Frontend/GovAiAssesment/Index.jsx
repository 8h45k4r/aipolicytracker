import React, { useState } from "react";
import { AppLayout } from "@/Layouts/AppLayout";
import { Head } from "@inertiajs/react";
import PublicPurpose from "./Components/PublicPurpose";
import HumanCentredValue from "./Components/HumanCentredValue";

export default function Index() {
    const [questions, setQuestions] = useState([]);

    const handleFormUpdate = (updatedQuestions) => {
        setQuestions(updatedQuestions); // Update the parent's state
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        // Perform form submission logic here
    };

    return (
        <AppLayout>
            <Head title="Timeline" />
            <div className="block md:relative top-[-60px] w-full ">
                <div className="lg:border rounded-md w-full md:bg-white py-5">
                    <div className=" md:border-b-2 pb-4 lg:px-5 flex justify-between items-center">
                        <div>
                            <p className="font-bold text-primary-light text-lg">
                                GovAI Assesment
                            </p>
                        </div>
                    </div>

                    <div className="p-5">
                        <form onSubmit={handleSubmit}>
                            <PublicPurpose onFormUpdate={handleFormUpdate} />
                            {/* <HumanCentredValue /> */}
                            <button
                                type="submit"
                                className="mt-4 bg-blue-500 text-white px-4 py-2 rounded"
                            >
                                Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
