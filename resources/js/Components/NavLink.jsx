import { Link } from '@inertiajs/react';

export default function NavLink({ active = false, className = '', children, ...props }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-brand-cyan text-brand-navy focus:border-brand-cyan '
                    : 'border-transparent text-brand-muted hover:text-brand-navy hover:border-brand-line focus:text-brand-navy focus:border-brand-line ') +
                className
            }
        >
            {children}
        </Link>
    );
}
