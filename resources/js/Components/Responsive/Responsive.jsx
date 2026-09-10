import { useState, useEffect } from "react";

const Responsive = ({ children, responsive }) => {
    const [isVisible, setIsVisible] = useState(false);

    const checkVisibility = () => {
        const screenWidth = window.innerWidth;

        // Define the breakpoints
        const breakpoints = {
            sm: 640,
            md: 768,
            lg: 1024,
            xl: 1280,
        };

        // Check if screen width matches any of the provided breakpoints
        let show = responsive.some((bp) => {
            if (bp === "sm") return screenWidth < breakpoints["md"];
            if (bp === "md") return screenWidth >= breakpoints["md"] && screenWidth < breakpoints["lg"];
            if (bp === "lg") return screenWidth >= breakpoints["lg"] && screenWidth < breakpoints["xl"];
            if (bp === "xl") return screenWidth >= breakpoints["xl"];
            return false;
        });

        setIsVisible(show);
    };

    useEffect(() => {
        // Check visibility on component mount
        checkVisibility();

        // Listen for resize events
        window.addEventListener("resize", checkVisibility);

        // Cleanup listener on component unmount
        return () => {
            window.removeEventListener("resize", checkVisibility);
        };
    }, [responsive]);

    return isVisible ? <>{children}</> : null;
};

export default Responsive;
