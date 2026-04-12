<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'login' => [
        'title' => 'Login',
        'welcome' => 'Welcome to :name! 👋',
        'description' => 'Please sign-in to your account and start the adventure',
        'email' => 'Email',
        'email_placeholder' => 'john@example.com',
        'password' => 'Password',
        'forgot_password' => 'Forgot Password?',
        'remember_me' => 'Remember Me',
        'sign_in' => 'Sign in',
        'new_platform' => 'New on our platform?',
        'create_account' => 'Create an account',
    ],
    'confirm_password' => [
        'title' => 'Confirm Password',
        'description' => 'Please confirm your password before continuing.',
        'enter_password' => 'Enter Password',
        'confirm_button' => 'Confirm Password',
    ],
    'register' => [
        'title' => 'Register Page',
        'heading' => 'Adventure starts here 🚀',
        'description' => 'Make your app management easy and fun!',
        'username' => 'Username',
        'username_placeholder' => 'johndoe',
        'email' => 'Email',
        'email_placeholder' => 'john@example.com',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'terms_agree' => 'I agree to the',
        'privacy_policy' => 'privacy policy',
        'terms' => 'terms',
        'sign_up' => 'Sign up',
        'already_account' => 'Already have an account?',
        'sign_in' => 'Sign in instead',
    ],
    'forgot_password' => [
        'title' => 'Forgot Password',
        'heading' => 'Forgot Password? 🔒',
        'description' => 'Enter your email and we\'ll send you instructions to reset your password',
        'email' => 'Email',
        'email_placeholder' => 'john@example.com',
        'send_reset_link' => 'Send Reset Link',
        'back_to_login' => 'Back to login',
    ],
    'reset_password' => [
        'title' => 'Reset Password',
        'heading' => 'Reset Password 🔒',
        'email' => 'Email',
        'new_password' => 'New Password',
        'confirm_password' => 'Confirm Password',
        'set_password' => 'Set new password',
        'back_to_login' => 'Back to login',
    ],
    'registration' => [
        'billing_title' => 'Complete your registration',
        'billing_heading' => 'Payment required',
        'billing_plan_sidebar_registration' => 'Link this registration product in subscription products to show name and price here. The amount charged follows the Stripe price used for registration checkout.',
        'billing_description' => 'Complete checkout with Stripe to unlock the platform.',
        'pay_with_stripe' => 'Pay with Stripe',
        'sign_out' => 'Sign out',
        'qr_title' => 'Next step: WhatsApp',
        'qr_heading' => 'Link your WhatsApp',
        'qr_description' => 'Your plan is active. Connect the phone where you will handle customer messages by scanning the QR code (the same one shown in Chat when the local connector is enabled).',
        'qr_whatsapp_steps_local' => [
            'Open WhatsApp on the phone you want to use for this business.',
            'Menu (⋮) or Settings → Linked devices → Link a device.',
            'Point the camera at the QR code below and confirm on the phone.',
            'When the session is linked, go to the dashboard or open Chat to verify messages.',
        ],
        'qr_whatsapp_intro_cloud' => 'If you use WhatsApp Cloud / Twilio, linking your number is done from the Chat screen and your provider settings; no QR code is shown here.',
        'qr_whatsapp_already_connected' => 'WhatsApp is already connected for this team. You can go to the dashboard or open Chat.',
        'qr_whatsapp_image_alt' => 'QR code to link WhatsApp',
        'qr_whatsapp_refresh' => 'Refresh QR code',
        'qr_whatsapp_refresh_failed' => 'Could not refresh the code. Try again or open Chat.',
        'qr_open_chat' => 'Open Chat',
        'qr_continue_dashboard' => 'Go to dashboard',
        'invalid_payment_session' => 'Invalid payment session.',
        'welcome_plan_active' => 'Welcome! Your plan is active.',
        'confirm_payment_failed' => 'We could not confirm your payment. Please try again.',
        'stripe_not_configured' => 'Stripe is not configured for registration billing.',
        'product_not_configured' => 'Registration product is not configured. Set REGISTRATION_STRIPE_PRODUCT_ID or sync subscription_products.',
        'no_valid_price' => 'No valid Stripe price for this product. Synchronize products from Stripe.',
        'billing_customer_failed' => 'Could not create a billing customer. Check your Stripe configuration.',
        'price_load_failed' => 'Could not load price from Stripe.',
        'checkout_start_failed' => 'Payment session could not be started. Try again or contact support.',
    ],
    'two_factor' => [
        'title' => 'Two Step Verification',
        'heading' => 'Two Step Verification 💬',
        'auth_description' => 'Please confirm access to your account by entering the authentication code provided by your authenticator application.',
        'recovery_description' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
        'code_label' => 'Code',
        'recovery_code_label' => 'Recovery Code',
        'use_recovery' => 'Use a recovery code',
        'use_authentication' => 'Use an authentication code',
        'login_button' => 'Log in',
    ],

];
