import LogoImage from "@/assets/images/ai_logo.png";
import { Link } from "@inertiajs/react";
export function HeaderLogo() {
    return (
        <Link href={'/'}>
            <div className="header-logo">
                <img
                    src={LogoImage}
                    alt="AI Policy Tracker"
                    className="w-full h-full"
                />
            </div>
        </Link>
    );
}
