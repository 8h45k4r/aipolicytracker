import React from "react";

export default function Status({ statuses, statusState, handleStatusChange1, handleShowAll }) {
    return (
        <div className="flex gap-[24px] border px-[12px] py-[8px] rounded-md flex-wrap">
            {statuses
                .filter(status => status.label !== 'whitepaper' && status.label !== 'pilot')
                .map((status, index) => (
                    <div className="flex gap-0 items-center" key={index}>
                        <input
                            type="checkbox"
                            id={status.value}
                            checked={statusState[status.value] || false}
                            onChange={(e) => handleStatusChange1(status.value, e.target.checked)}
                            className={`rounded focus:ring-transparent focus:border-0 custom-input ${status.label}`}
                        />
                        <label htmlFor={status.value} className="capitalize" style={{ fontSize: '14px' }}>
                            {status.label}
                        </label>
                    </div>
                ))}

            <button
                type="button"
                className="text-blue-400 hover:underline"
                style={{ fontSize: '14px' }}
                onClick={handleShowAll} // Add onClick handler
            >
                All
            </button>
        </div>
    );
}
