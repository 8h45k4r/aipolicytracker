export default function InputLabel({ value, className = '', children, ...props }) {
    return (
        <label {...props} className={`block font-medium text-sm text-brand-navy ` + className}>
            {value ? value : children}
        </label>
    );
}
