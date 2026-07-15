tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            "colors": {
                "primary": "#006e25",
                "on-primary": "#ffffff",
                "primary-container": "#28a745",
                "on-primary-container": "#00330d",
                "secondary": "#7c5800",
                "on-secondary": "#ffffff",
                "secondary-container": "#feb700",
                "on-secondary-container": "#6b4b00",
                "tertiary": "#ae3200",
                "on-tertiary": "#ffffff",
                "tertiary-container": "#ff591f",
                "on-tertiary-container": "#541300",
                "error": "#ba1a1a",
                "on-error": "#ffffff",
                "error-container": "#ffdad6",
                "on-error-container": "#93000a",
                
                // Dark mode adaptations or custom colors mapped dynamically
                "background": "var(--background, #f9f9fc)",
                "on-background": "var(--on-background, #1a1c1e)",
                "surface": "var(--surface, #f9f9fc)",
                "on-surface": "var(--on-surface, #1a1c1e)",
                "surface-variant": "var(--surface-variant, #e2e2e5)",
                "on-surface-variant": "var(--on-surface-variant, #3e4a3c)",
                "outline": "var(--outline, #6e7b6b)",
                "outline-variant": "var(--outline-variant, #bdcab9)",
                
                "surface-bright": "#f9f9fc",
                "surface-dim": "#dadadc",
                "surface-container-lowest": "var(--surface-container-lowest, #ffffff)",
                "surface-container-low": "var(--surface-container-low, #f3f3f6)",
                "surface-container": "var(--surface-container, #eeeef0)",
                "surface-container-high": "var(--surface-container-high, #e8e8ea)",
                "surface-container-highest": "var(--surface-container-highest, #e2e2e5)"
            },
            "borderRadius": {
                "DEFAULT": "0.25rem",
                "lg": "0.5rem",
                "xl": "0.75rem",
                "full": "9999px"
            },
            "spacing": {
                "xs": "4px",
                "lg": "48px",
                "sm": "12px",
                "md": "24px",
                "margin-mobile": "16px",
                "gutter": "16px",
                "margin-desktop": "32px",
                "base": "8px"
            },
            "fontFamily": {
                "display-lg": ["Hanken Grotesk"],
                "body-lg": ["Plus Jakarta Sans"],
                "headline-lg": ["Hanken Grotesk"],
                "body-md": ["Plus Jakarta Sans"],
                "label-sm": ["Plus Jakarta Sans"],
                "title-md": ["Hanken Grotesk"],
                "headline-lg-mobile": ["Hanken Grotesk"],
                "label-bold": ["Hanken Grotesk"]
            },
            "fontSize": {
                "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "800"}],
                "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                "headline-lg": ["32px", {"lineHeight": "40px", "fontWeight": "700"}],
                "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                "title-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                "headline-lg-mobile": ["28px", {"lineHeight": "34px", "fontWeight": "700"}],
                "label-bold": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "700"}]
            }
        },
    },
};
