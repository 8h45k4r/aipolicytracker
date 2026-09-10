import React from "react";
import { usePage } from "@inertiajs/react";
import { NavBar } from "@/Layouts/NavBar/NavBar";

export function Header() {
    const { site } = usePage().props;
    const links = site?.links ?? {};

    const NavBarLists = {
        dashboard: { name: "Home", url: route("frontend.dashboard") },
        ...(links.airis ? { airis: { name: "airis", url: links.airis } } : {}),
        watchlist: { name: "Bookmarks", url: route("frontend.watch_list.index") },
        news: { name: "news", url: route("news.index") },
        timeline: { name: "Timeline", url: route("frontend.time_line.index") },
        ...(links.whitepaper
            ? { whitepaper: { name: "whitepaper", url: links.whitepaper } }
            : {}),
    };

    return (
        <header className="header md:px-16">
            <NavBar NavBarLists={NavBarLists} />
        </header>
    );
}
