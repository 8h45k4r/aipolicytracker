import React, { useEffect } from "react";
import { Header } from "./Header/Header";
import { Link, usePage } from "@inertiajs/react";
import Footer from "./Footer/Footer";

// import { usePage } from "@inertiajs/react";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";

export function AppLayout({ children }) {
    const { props } = usePage();
    // Extract flash messages
    const successMessage = props.flash?.success;
    const errorMessage = props.flash?.error;

    // Show toast notifications
    useEffect(() => {
        if (successMessage) {
            toast.success(successMessage);
        }
        if (errorMessage) {
            toast.error(errorMessage);
        }
    }, [successMessage, errorMessage]);
    return (
        <main className="container-wrapper">
            {/* header */}
            <Header />
            {/* content here */}
            <section className="px-[22px] md:px-[64px] pb-[16px] flex-1">
                {children}
            </section>

            {/* footer */}
            <Footer />

            {/* ToastContainer for toast notifications */}
            <ToastContainer />
        </main>
    );
}
