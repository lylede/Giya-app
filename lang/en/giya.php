<?php

/*
|--------------------------------------------------------------------------
| GIYA - interface strings
|--------------------------------------------------------------------------
| The source of truth. lang/ceb and lang/fil mirror these keys exactly; a
| key missing from one of those falls back to the English here rather than
| printing "giya.nav.home" at a devotee, so a partial translation degrades
| quietly instead of breaking a page.
|
| Admin screens are deliberately not translated - a staff panel in English
| is the norm, and the terms there are administrative rather than devotional.
*/

return [

    'nav' => [
        'home'           => 'Home',
        'map'            => 'Map',
        'plan'           => 'Plan',
        'profile'        => 'Profile',
        'search'         => 'Search churches…',
        'search_label'   => 'Search churches',
        'notifications'  => 'Notifications',
        'mark_all_read'  => 'Mark all read',
        'loading'        => 'Loading…',
        'sign_in'        => 'Sign In',
        'sign_out'       => 'Sign Out',
        'create_account' => 'Create Account',
        'region'         => 'Metro Cebu',
        'toggle'         => 'Toggle navigation',
    ],

    'footer' => [
        'explore'        => 'Explore',
        'find_churches'  => 'Find Churches',
        'plan_route'     => 'Plan Route',
        'visita'         => 'Visita Iglesia',
        'chatbot'        => 'AI Chatbot',
        'account'        => 'Account',
        'my_profile'     => 'My Profile',
        'my_itineraries' => 'My Itineraries',
        'sign_in_plan'   => 'Sign In to Plan',
        'register'       => 'Register',
        'create_account' => 'Create an Account',
        'dashboard'      => 'Dashboard',
    ],

    'common' => [
        'save'        => 'Save',
        'cancel'      => 'Cancel',
        'close'       => 'Close',
        'delete'      => 'Delete',
        'search'      => 'Search',
        'clear'       => 'Clear',
        'back'        => 'Back',
        'next'        => 'Next',
        'view'        => 'View Details',
        'coming_soon' => 'Coming soon',
        'optional'    => 'optional',
    ],

    'church' => [
        'churches'      => 'Churches',
        'church'        => 'Church',
        'mass_schedule' => 'Mass Schedule',
        'open_now'      => 'Open Now',
        'directions'    => 'Directions',
        'add_to_route'  => 'Add to route',
        'see_details'   => 'See details',
        'results'       => ':count results',
        'near'          => 'Near',
    ],

    'map' => [
        'eyebrow'      => 'EXPLORE',
        'title'        => 'Map of Metro Cebu',
        'lead'         => 'Find churches near you, then build a route through the ones you want to visit.',
        'explore'      => 'Explore Churches',
        'filters'      => 'Filters',
        'selected'     => ':count churches selected',
        'plan_route'   => 'Plan Route',
        'locate'       => 'Find my location',
        'fullscreen'   => 'Fullscreen',
        'zoom_in'      => 'Zoom in',
        'zoom_out'     => 'Zoom out',
        'pick_first'   => 'Pick at least one destination first.',
        'located'      => 'Showing your location. The nearest destinations are listed first.',
    ],

    'plan' => [
        'hub'            => 'Pilgrimage Plan Hub',
        'plan_journey'   => 'Plan Your Journey',
        'recent'         => 'Recent Itineraries',
        'tips'           => 'Pilgrim Tips',
        'create_first'   => 'Create My First Plan',
        'my_itineraries' => 'My Itineraries',
        'visita'         => 'Visita Iglesia',
        'new_plan'       => 'New Plan',
        'notes'          => 'Notes',
        'start_time'     => 'Start Time',
        'date'           => 'Date',
        'destinations'   => 'Add Destinations',
        'stops_visited'  => ':done of :total stops visited',
        'complete'       => ':percent% complete',
        'current_stop'   => 'Current stop',
        'mark_visited'   => 'Mark Current Visited',
        'end'            => 'End Pilgrimage',
        'all_itineraries'=> 'All itineraries',
        'finished'       => 'Pilgrimage complete',
    ],

    'profile' => [
        'overview'       => 'Overview',
        'visit_history'  => 'Visit History',
        'itineraries'    => 'Itineraries',
        'favorites'      => 'Favorites',
        'preferences'    => 'Preferences',
        'edit'           => 'Edit Profile',
        'pilgrimages'    => 'Pilgrimages',
        'completed'      => 'Completed',
        'visited'        => 'Visited',
        'reviews'        => 'Reviews',
        'written'        => 'Written',
        'achievements'   => 'Pilgrim Achievements',
        'account'        => 'Account',
        'account_info'   => 'Account Information',
        'full_name'      => 'Full Name',
        'email'          => 'Email Address',
        'current_pw'     => 'Current Password',
        'new_pw'         => 'New Password',
        'confirm_pw'     => 'Confirm New Password',
        'appearance'     => 'Appearance',
        'font_size'      => 'Font Size',
        'language'       => 'Language',
        'display_lang'   => 'Display Language',
        'lang_note'      => 'The interface changes as soon as you pick one.',
        'notifications'  => 'Notifications',
        'avatar_hint'    => 'JPG, PNG, or WEBP. Up to 2 MB.',
        'sign_out_note'  => 'End your session on this device.',
        'open_plan_hub'  => 'Open Plan Hub',
        'find_churches'  => 'Find Churches',
    ],

    'chat' => [
        'title'        => 'Giya AI Assistant',
        'subtitle'     => 'Pilgrimage guide for Metro Cebu',
        'offline'      => 'Offline - answering from records',
        'new_chat'     => 'New chat',
        'placeholder'  => 'Ask about churches, mass times, or a route…',
        'send'         => 'Send',
        'greeting'     => 'Maayong buntag! I am Giya AI. Ask me about churches in Metro Cebu.',
        'note'         => "Giya AI answers from GIYA's destination records. Mass schedules change - please confirm with the parish before travelling.",
        'open_full'    => 'Open the full chat page',
    ],
];
