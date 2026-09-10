import defaultLogo from "@/assets/images/ai_logo.png";
import { usePage } from "@inertiajs/react";

export default function ApplicationLogo({ className = " ", ...props }) {
    const { logo } = usePage().props;
    return (
        <div className={className}>
            <img
                // src={Logo}
                src={logo?.file_path ? `/storage/${logo?.file_path}` : defaultLogo}
                {...props}
                alt=""
                className="w-full h-full"
            />
        </div>
    );
}
