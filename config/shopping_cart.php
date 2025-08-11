<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default cart instance
    |--------------------------------------------------------------------------
    |
    | This option controls the default cart connection that gets used while
    | using this cart library. This connection is used when another is
    | not explicitly specified when executing a given cart function.
    |
    */

    'default' => env('CART_INSTANCE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Cart instances
    |--------------------------------------------------------------------------
    |
    | Here you may configure the cart instances for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses database storage to persist your cart data.
    |
    | Supported: "session", "database", "cache"
    |
    */

    'instances' => [

        'default' => [
            'storage' => null,
            'events' => true,
            'instance_name' => 'default',
            'session_key' => '88uuiioo99888',
        ],

        'wishlist' => [
            'storage' => null,
            'events' => true,
            'instance_name' => 'wishlist',
            'session_key' => '88uuiioo99888',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cart storage
    |--------------------------------------------------------------------------
    |
    | Configure the storage driver for your cart. You can use session,
    | database, or cache. Session is the default and most common choice.
    |
    */

    'storage' => null,

    /*
    |--------------------------------------------------------------------------
    | Cart events
    |--------------------------------------------------------------------------
    |
    | Enable cart events to track cart changes. This will fire events
    | when items are added, updated, or removed from the cart.
    |
    */

    'events' => true,

    /*
    |--------------------------------------------------------------------------
    | Cart instance name
    |--------------------------------------------------------------------------
    |
    | The default name for your cart instance. You can change this to
    | whatever you prefer.
    |
    */

    'instance_name' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Cart session key
    |--------------------------------------------------------------------------
    |
    | The session key used to store cart data. This should be unique
    | to avoid conflicts with other applications.
    |
    */

    'session_key' => '88uuiioo99888',

];
