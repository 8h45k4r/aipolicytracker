import React from "react";

export default function HistoricState({ date }) {
    return (
        <div className="border rounded-md px-3 py-2">
            <p>Historic state</p>

            <div className="flex items-center gap-10 mt-9">
                <span className="text-xs font-bold text-secondary">2024</span>
                <div className="w-full relative">
                    <div className="before-cone bg-primary font-bold rounded-md right-0 bottom-5 text-white w-fit px-5 py-2 absolute">
                        {date}
                    </div>
                    <p className="before-dot h-[3px] rounded-lg w-full bg-secondary flex items-center"></p>
                </div>

                <span className="text-xs font-bold text-secondary">Now</span>
            </div>
        </div>
    );
}
