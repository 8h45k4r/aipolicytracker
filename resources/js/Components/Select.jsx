import { useEffect, useRef } from "react";
import Select from "react-select";
import makeAnimated from "react-select/animated";

const animatedComponents = makeAnimated();

export default function SelectInput(
    {
        className = "",
        isFocused = false,
        options = null,
        label,
        errorMsg = null,
        ...props
    },
    ref
) {
    const localRef = useRef();
    const input = ref ? ref : localRef;

    useEffect(() => {
        if (isFocused) {
            input.current.focus();
        }
    }, [isFocused]);

    return (
        <div className="">
            <label
                className="block tracking-wide text-gray-500 text-sm font-bold mb-2"
                htmlFor="grid-last-name"
            >
                {label}
            </label>

            <Select
                id="select"
                closeMenuOnSelect={true}
                components={animatedComponents}
                options={options}
                {...props}
            />

            {errorMsg && <p className=" text-red-500 text-xs mt-1">{errorMsg}</p>}
        </div>
    );
}
