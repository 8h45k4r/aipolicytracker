import { forwardRef, useEffect, useRef } from 'react';

export default forwardRef(function TextInput({ type = 'text', className = '', isFocused = false, ...props }, ref) {
    const localRef = useRef();
    const input = ref ? ref : localRef;

    useEffect(() => {
        if (isFocused) {
            input.current.focus();
        }
    }, []);

    return (
        <input
            {...props}
            type={type}
            className={
                `border-gray-300 w-full focus:border-indigo-500 rounded-md shadow-sm ${className} focus:ring-blue-900`
                }
            ref={input}
        />
    );
});
