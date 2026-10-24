import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ active = false, className = '', children, ...props }) {
    return (
        <Link
            {...props}
            className={`w-full flex items-start ps-3 pe-4 py-2 border-l-4 ${
                active
                    ? 'border-brand-cyan text-brand-navy bg-brand-paper focus:text-brand-ink focus:bg-brand-paper focus:border-brand-cyan'
                    : 'border-transparent text-brand-body hover:text-brand-navy hover:bg-brand-paper hover:border-brand-line focus:text-brand-navy focus:bg-brand-paper focus:border-brand-line'
            } text-base font-medium focus:outline-none transition duration-150 ease-in-out ${className}`}
        >
            {children}
        </Link>
    );
}
