import { Link } from "@inertiajs/react";

const YEAR = new Date().getFullYear();

export default function Guest({ children }) {
    return (
        <div className="flex min-h-screen flex-col bg-brand-paper">
            <header className="px-4 py-4 sm:px-6">
                <Link href="/" aria-label="AI Policy Tracker home">
                    <img
                        src="/brand/logo-on-light.svg"
                        alt="AI Policy Tracker"
                        className="h-7 w-auto"
                    />
                </Link>
            </header>
            <main className="flex flex-1 flex-col items-center justify-center px-4 py-8 sm:px-6">
                {children}
            </main>
            <footer className="px-4 py-4 text-center text-xs text-brand-muted sm:px-6">
                <a href="/" className="hover:text-brand-navy">
                    &copy; {YEAR} AI Policy Tracker
                </a>
            </footer>
        </div>
    );
}
