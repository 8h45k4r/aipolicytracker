import React from "react";
import { AppLayout } from "@/Layouts/AppLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import Description from "../Dashboard/Components/Description/Description";

import DescriptionData from "@/Pages/Frontend/Dashboard/Components/Description/DescriptionData";

export default function AboutAiPolicy() {
    const { site } = usePage().props;
    const contactLists = (site?.contact_emails ?? []).map((email) => ({
        icon: "fa-regular fa-envelope",
        name: email,
        url: `mailto:${email}`,
        type: "email",
    }));
    const contributorLists = (site?.contributors ?? []).map((c) => ({
        icon: c.icon ?? "fa-solid fa-user",
        name: c.name,
        url: c.url,
    }));
    return (
        <AppLayout>
            <Head title="Denied permission" />
            <div className="block md:relative top-[-60px] w-full ">
                <div className="lg:border rounded-md w-full md:bg-white py-5">
                    <Description
                        descriptionData={DescriptionData}
                        contributorLists={contributorLists}
                        contactLists={contactLists}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
