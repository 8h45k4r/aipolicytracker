import Select from "react-select";
import makeAnimated from "react-select/animated";

const SelectInput = ({
    label,
    options,
    value,
    onChange,
    className = "",
    placeholder = "Select",
}) => (
    <div className="">
        {label && (
               <label
               className={`block text-sm font-medium text-gray-700 ${className}`}
           >
               {label}
           </label>
        )}
        {/* <label
            className={`block text-sm font-medium text-gray-700 ${className}`}
        >
            {label}
        </label> */}
        <Select
            id="select"
            components={makeAnimated()}
            options={options}
            isMulti
            value={options.filter((option) => value.includes(option.value))}
            onChange={onChange}
            placeholder={placeholder}
        />
    </div>
);

export default SelectInput;
