export default function Checkbox({ className = "", ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={`
                rounded border-brand-line text-brand-navy shadow-sm focus:ring-brand-cyan
                ${className || ""}
              `}
        />
    );
}
