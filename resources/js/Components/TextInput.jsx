import { useEffect, useRef } from 'react';

// React 19 passes ref as an ordinary prop, so forwardRef is no longer needed.
export default function TextInput({ type = 'text', className = '', isFocused = false, ref, ...props }) {
    const localRef = useRef(null);
    const inputRef = ref ?? localRef;

    useEffect(() => {
        if (isFocused) {
            inputRef.current?.focus();
        }
    }, [isFocused, inputRef]);

    return (
        <input
            {...props}
            type={type}
            className={
                `border-gray-300 w-full focus:border-indigo-500 rounded-md shadow-sm ${className} focus:ring-blue-900`
                }
            ref={inputRef}
        />
    );
}
