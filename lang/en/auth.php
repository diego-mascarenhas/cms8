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
        'invitation_heading' => 'You have been invited to join :team',
        'invitation_description' => 'Complete the form below to create your account and accept the invitation.',
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
        'qr_description' => 'Your plan is active. Follow the steps on your phone and use the QR code below to link WhatsApp.',
        'qr_whatsapp_steps_local' => [
            'Open WhatsApp on the phone you will use for this business.',
            'Tap Menu (⋮) or Settings → Linked devices → Link a device.',
            'When the camera opens, point it at the QR code shown below on this screen and confirm on the phone if WhatsApp asks.',
            'When it is linked, go to the dashboard or open Chat to check your messages.',
        ],
        'qr_whatsapp_steps_chat' => [
            'Scan the QR code below to open Humano on your phone.',
            'Open the WhatsApp app on that same phone.',
            'Tap Menu (⋮) or Settings → Linked devices → Link a device.',
            'When the camera opens, use the QR code shown in Humano and confirm in WhatsApp if prompted.',
        ],
        'qr_whatsapp_already_connected' => 'WhatsApp is already connected for this team. You can go to the dashboard or open Chat.',
        'qr_whatsapp_image_alt' => 'QR code to link WhatsApp',
        'qr_whatsapp_refresh' => 'Refresh QR code',
        'qr_whatsapp_refresh_hint' => 'If the code does not appear, tap refresh — we also update it automatically when you open Chat.',
        'qr_whatsapp_timing_hint' => 'The QR code may take up to 45 seconds to appear. Do not close this screen.',
        'qr_whatsapp_loading' => 'Loading QR code…',
        'qr_whatsapp_refresh_failed' => 'Could not refresh the code. Try again or open Chat.',
        'qr_whatsapp_service_unreachable' => 'We could not load the QR code right now. Wait a few seconds or tap Refresh QR code.',
        'qr_whatsapp_load_failed' => 'The QR code did not appear. Tap Refresh QR code to try again.',
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
