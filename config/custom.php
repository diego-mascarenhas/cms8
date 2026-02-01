<?php

// Custom Config
// -------------------------------------------------------------------------------------
// ! IMPORTANT: Make sure you clear the browser local storage In order to see the config changes in the template.
// ! To clear local storage: (https://www.leadshook.com/help/how-to-clear-local-storage-in-google-chrome-browser/).

return [
    'custom' => [
        'myLayout' => 'vertical', // Options[String]: vertical(default), horizontal
        'myTheme' => 'theme-default', // Options[String]: theme-default(default), theme-bordered, theme-semi-dark
        'myStyle' => 'system', // Options[String]: light(default), dark & system mode
        'myRTLSupport' => true, // options[Boolean]: true(default), false // To provide RTLSupport or not
        'myRTLMode' => false, // options[Boolean]: false(default), true // To set layout to RTL layout  (myRTLSupport must be true for rtl mode)
        'hasCustomizer' => true, // options[Boolean]: true(default), false // Display customizer or not THIS WILL REMOVE INCLUDED JS FILE. SO LOCAL STORAGE WON'T WORK
        'displayCustomizer' => false, // options[Boolean]: true(default), false // Display customizer UI or not, THIS WON'T REMOVE INCLUDED JS FILE. SO LOCAL STORAGE WILL WORK
        'contentLayout' => 'compact', // options[String]: 'compact', 'wide' (compact=container-xxl, wide=container-fluid)
        'navbarType' => 'sticky', // options[String]: 'sticky', 'static', 'hidden' (Only for vertical Layout)
        'footerFixed' => false, // options[Boolean]: false(default), true // Footer Fixed
        'showFooter' => false, // options[Boolean]: true(default), false // Show or hide footer
        'menuFixed' => true, // options[Boolean]: true(default), false // Layout(menu) Fixed (Only for vertical Layout)
        'menuCollapsed' => false, // options[Boolean]: false(default), true // Show menu collapsed, (Only for vertical Layout)
        'headerType' => 'fixed', // options[String]: 'static', 'fixed' (for horizontal layout only)
        'showDropdownOnHover' => true, // true, false (for horizontal layout only)
        'customizerControls' => [
            'rtl',
            'style',
            'headerType',
            'contentLayout',
            'layoutCollapsed',
            'layoutNavbarOptions',
            'themes',
        ], // To show/hide customizer options
        'showRegister' => true, // options[Boolean]: false(default), true // Show or hide register button on login page
        // REVIEW - TeamManager true/false
        'TeamManager' => false,
        'animateLogo' => true, // options[Boolean]: true(default), false // To enable or disable logo animation
        'showSearch' => true, // options[Boolean]: true(default), false // To enable or disable the search bar
        // 'showLanguageSelector' => false, // options[Boolean]: true(default), false // To enable or disable the language selector
        // 'showQuickAccess' => true, // options[Boolean]: true(default), false // To enable or disable quick access links
        // 'showNotifications' => true, // options[Boolean]: true(default), false // To enable or disable notifications
    ],
    
    // Default category ID to assign to new contacts/leads (null to disable). Same env name as Mobile: DEFAULT_CATEGORY_ID
    'default_contact_category_id' => env('DEFAULT_CATEGORY_ID', env('DEFAULT_CONTACT_CATEGORY_ID', null)),
];
