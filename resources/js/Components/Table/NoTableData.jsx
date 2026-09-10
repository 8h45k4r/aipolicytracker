import React from "react";

export default function NoTableData({ noTableDataTitle = "No Data" }) {
    return (
        <div className="mt-2 flex justify-center items-center h-[400px]">
            <div>
                <h1 className="font-bold text-primary text-lg text-center">
                    {noTableDataTitle}
                </h1>
                <div className="flex gap-4 mt-5">
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[350px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                </div>
                <div className="flex gap-4 mt-5">
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[350px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                </div>
                <div className="flex gap-4 mt-5">
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[350px]"></div>
                    <div className="bg-secondary h-[35px] rounded-md w-[40px]"></div>
                </div>
            </div>
        </div>
    );
}
