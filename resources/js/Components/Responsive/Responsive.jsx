import { useCallback, useSyncExternalStore } from "react";

// Tailwind's breakpoints: sm is anything below md.
const breakpoints = { md: 768, lg: 1024, xl: 1280 };

const matches = (bp, width) => {
    if (bp === "sm") return width < breakpoints.md;
    if (bp === "md") return width >= breakpoints.md && width < breakpoints.lg;
    if (bp === "lg") return width >= breakpoints.lg && width < breakpoints.xl;
    if (bp === "xl") return width >= breakpoints.xl;
    return false;
};

const subscribe = (onChange) => {
    window.addEventListener("resize", onChange);
    return () => window.removeEventListener("resize", onChange);
};

// Renders its children only at the listed breakpoints. The window width is read as an
// external store, so a resize re-renders without setting state inside an effect.
const Responsive = ({ children, responsive }) => {
    const getSnapshot = useCallback(() => responsive.some((bp) => matches(bp, window.innerWidth)), [responsive]);
    const isVisible = useSyncExternalStore(subscribe, getSnapshot, () => false);

    return isVisible ? <>{children}</> : null;
};

export default Responsive;
