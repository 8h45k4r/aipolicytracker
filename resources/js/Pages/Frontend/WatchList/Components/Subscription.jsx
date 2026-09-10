import Button from "@/Components/Button";
import React from "react";

export default function Subscription({ className = "" }) {
    return (
        <div className={`rounded-md w-full bg-white py-5 mt-5 ${className}`}>
            <div className="border-b-2 pb-4 px-5">
                <h1 className="font-bold text-primary text-lg">
                    Subscription settings
                </h1>
            </div>

            <div className=" mt-5 px-5">
                <p className="text-gray-600 text-sm">
                    Enter your e-mail to receive updates of the chosen (AI)
                    policy tracker. If you have already subscribed, the list of
                    (AI) policy tracker will be changed to the current state.
                </p>
                <p className="text-gray-600 text-sm mt-5">
                    Also you can cancel all the subscriptions.
                </p>

                {/* subscription form */}

                <div className="flex items-center mt-5 gap-5">
                    <div className="mb-6 w-1/2">
                        <label
                            className="block text-gray-700 text-sm  mb-2"
                            htmlFor="email"
                        >
                            Your e-mail
                        </label>
                        <input
                            className="w-full bg-gray-50 focus:bg-white rounded px-3 border border-gray-200"
                            id="email"
                            type="email"
                            placeholder="Enter your e-mail..."
                        />
                        {/* <p className="text-red-500 text-xs italic">
                            Please choose a email.
                        </p> */}
                    </div>
                    <div className=" w-1/2 flex gap-5">
                        <Button>Subscribe</Button>
                        <Button
                            className="border-2 rounded-lg hover:border-blue-900"
                            style={{ background: "white" }}
                        >
                            Unubscribe
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
