<?php

/*
|--------------------------------------------------------------------------
| Storefront themes
|--------------------------------------------------------------------------
| Each theme = views in resources/views/themes/{key} (falling back to
| themes/base) + compiled CSS in public/assets/store/css/{key}.css.
| `sections` are the default homepage sections seeded for that theme;
| admins can reorder, toggle and edit them in Storefront → Homepage.
*/

return [

    'noir' => [
        'name' => 'Noir Tech',
        'tagline' => 'Dark, premium, product-first',
        'description' => 'Near-black canvas, one electric lime accent and big device imagery. Feels like a flagship launch page.',
        'dark' => true,
        'fonts' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap',
        'preview' => ['bg' => '#0A0B0D', 'surface' => '#16181D', 'text' => '#EDEFF2', 'primary' => '#C6FF3D', 'accent' => '#7C5CFF'],
        'gallery' => 'thumbs-left',
        'sections' => [
            ['type' => 'hero', 'title' => null, 'settings' => ['slides' => [
                ['badge' => 'Laser-cut in 5 minutes', 'title' => 'Armor that fits like it was born there.', 'subtitle' => 'Precision hydrogel, privacy glass and carbon wraps cut to the millimetre for 300+ devices.', 'button_text' => 'Shop protection', 'button_url' => '/shop?category=screen-protection', 'image' => null],
            ]]],
            ['type' => 'categories', 'title' => 'Shop the essentials', 'subtitle' => 'Everything your phone needs, nothing it doesn’t.', 'settings' => ['limit' => 6]],
            ['type' => 'product_rail', 'title' => 'Signature picks', 'subtitle' => 'Our most-loved gear this month', 'settings' => ['source' => 'featured', 'limit' => 8]],
            ['type' => 'shop_by_device', 'title' => 'Find your fit', 'subtitle' => 'Pick your phone — we’ll show what fits it.', 'settings' => ['limit' => 8]],
            ['type' => 'flash_sale', 'title' => 'Flash drop', 'subtitle' => 'Gone when the timer hits zero', 'settings' => ['limit' => 4]],
            ['type' => 'promo_banner', 'settings' => ['banners' => [
                ['title' => 'Carbon. Matte. Skin.', 'subtitle' => 'Full-body wraps cut for your exact model.', 'button_text' => 'Explore wraps', 'button_url' => '/shop?category=wraps-skins', 'image' => null],
                ['title' => 'Charge at full speed', 'subtitle' => 'GaN chargers & braided cables built to last.', 'button_text' => 'Shop power', 'button_url' => '/shop?category=chargers', 'image' => null],
            ]]],
            ['type' => 'product_rail', 'title' => 'Just landed', 'subtitle' => null, 'settings' => ['source' => 'new', 'limit' => 8]],
            ['type' => 'testimonials', 'title' => 'Trusted across Pakistan', 'settings' => ['items' => [
                ['name' => 'Hamza R.', 'city' => 'Lahore', 'rating' => 5, 'text' => 'The privacy glass fits my S24 Ultra edge to edge. Cut in front of me in five minutes.'],
                ['name' => 'Ayesha K.', 'city' => 'Karachi', 'rating' => 5, 'text' => 'Ordered a carbon wrap and a cable at night, delivered in two days. Quality is unreal.'],
                ['name' => 'Bilal S.', 'city' => 'Islamabad', 'rating' => 4, 'text' => 'Finally a shop that actually has the exact model I need. Great packaging too.'],
            ]]],
            ['type' => 'newsletter', 'title' => 'Get first dibs on drops', 'subtitle' => 'New wraps, restocks and members-only codes. No spam.', 'settings' => ['button_text' => 'Subscribe']],
        ],
    ],

    'dealmart' => [
        'name' => 'Deal Mart',
        'tagline' => 'Bright, busy, built to convert',
        'description' => 'Marketplace energy: bold sale colours, flash-sale countdowns, dense grids and a big search bar.',
        'dark' => false,
        'fonts' => 'https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap',
        'preview' => ['bg' => '#F3F4F6', 'surface' => '#FFFFFF', 'text' => '#1E1E28', 'primary' => '#E62E2D', 'accent' => '#FFB800'],
        'gallery' => 'thumbs-bottom',
        'sections' => [
            ['type' => 'hero', 'settings' => ['slides' => [
                ['badge' => 'Mega Accessory Sale', 'title' => 'Up to 40% off cases, chargers & more', 'subtitle' => 'Free delivery on orders above the free-shipping limit. Cash on Delivery nationwide.', 'button_text' => 'Shop the sale', 'button_url' => '/deals', 'image' => null],
                ['badge' => 'New in', 'title' => 'Hydrogel & privacy glass for every model', 'subtitle' => 'Cut to fit in-store in minutes.', 'button_text' => 'Find yours', 'button_url' => '/shop?category=screen-protection', 'image' => null],
            ]]],
            ['type' => 'features', 'settings' => ['items' => [
                ['icon' => 'ri-truck-line', 'title' => 'Nationwide delivery', 'text' => '2–4 working days'],
                ['icon' => 'ri-hand-coin-line', 'title' => 'Cash on Delivery', 'text' => 'Pay when it arrives'],
                ['icon' => 'ri-shield-check-line', 'title' => 'Genuine products', 'text' => 'Checked before dispatch'],
                ['icon' => 'ri-customer-service-2-line', 'title' => 'WhatsApp support', 'text' => '10am – 10pm daily'],
            ]]],
            ['type' => 'flash_sale', 'title' => 'Flash Sale', 'subtitle' => 'Ending soon', 'settings' => ['limit' => 6]],
            ['type' => 'categories', 'title' => 'Shop by category', 'settings' => ['limit' => 8]],
            ['type' => 'product_rail', 'title' => 'Top selling', 'settings' => ['source' => 'bestsellers', 'limit' => 12]],
            ['type' => 'promo_banner', 'settings' => ['banners' => [
                ['title' => 'Power banks from Rs. 2,999', 'subtitle' => 'Fast charge, slim builds', 'button_text' => 'Shop now', 'button_url' => '/shop?category=power-banks', 'image' => null],
                ['title' => 'Combo deals', 'subtitle' => 'Bundle & save more', 'button_text' => 'View bundles', 'button_url' => '/deals', 'image' => null],
            ]]],
            ['type' => 'bundles', 'title' => 'Combo deals', 'subtitle' => 'Bundle up and save', 'settings' => ['limit' => 3]],
            ['type' => 'product_rail', 'title' => 'New arrivals', 'settings' => ['source' => 'new', 'limit' => 12]],
            ['type' => 'shop_by_device', 'title' => 'Shop by phone', 'settings' => ['limit' => 10]],
            ['type' => 'brands', 'title' => 'Top brands', 'settings' => []],
            ['type' => 'newsletter', 'title' => 'Never miss a deal', 'subtitle' => 'Subscribe for flash-sale alerts and exclusive coupons.', 'settings' => ['button_text' => 'Sign me up']],
        ],
    ],

    'studio' => [
        'name' => 'Studio Minimal',
        'tagline' => 'Calm, editorial, design-led',
        'description' => 'Warm off-white, serif headlines and generous whitespace. Lets the product photography do the talking.',
        'dark' => false,
        'fonts' => 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600&display=swap',
        'preview' => ['bg' => '#F6F3EE', 'surface' => '#FFFFFF', 'text' => '#1C1B19', 'primary' => '#1C1B19', 'accent' => '#C4602D'],
        'gallery' => 'grid',
        'sections' => [
            ['type' => 'hero', 'settings' => ['slides' => [
                ['badge' => 'The Fit Collection', 'title' => 'Made to fit. Nothing more.', 'subtitle' => 'Quietly precise protection and accessories, cut and curated for the phone you actually own.', 'button_text' => 'Explore the collection', 'button_url' => '/shop', 'image' => null],
            ]]],
            ['type' => 'categories', 'title' => 'Collections', 'settings' => ['limit' => 4]],
            ['type' => 'product_rail', 'title' => 'The edit', 'subtitle' => 'A small selection we keep coming back to.', 'settings' => ['source' => 'featured', 'limit' => 8]],
            ['type' => 'promo_banner', 'settings' => ['banners' => [
                ['title' => 'Skins, in natural tones', 'subtitle' => 'Matte textures that age beautifully.', 'button_text' => 'Discover', 'button_url' => '/shop?category=wraps-skins', 'image' => null],
                ['title' => 'Everyday power', 'subtitle' => 'Chargers and cables that simply work.', 'button_text' => 'Discover', 'button_url' => '/shop?category=chargers', 'image' => null],
            ]]],
            ['type' => 'shop_by_device', 'title' => 'Shop by device', 'settings' => ['limit' => 6]],
            ['type' => 'testimonials', 'title' => 'Kind words', 'settings' => ['items' => [
                ['name' => 'Sana M.', 'city' => 'Lahore', 'rating' => 5, 'text' => 'Beautifully packaged and the skin fits perfectly. It feels like a design store, not a phone shop.'],
                ['name' => 'Umar F.', 'city' => 'Faisalabad', 'rating' => 5, 'text' => 'Simple ordering, honest prices, fast delivery.'],
            ]]],
            ['type' => 'newsletter', 'title' => 'Letters from the studio', 'subtitle' => 'Occasional notes on new collections. Unsubscribe anytime.', 'settings' => ['button_text' => 'Subscribe']],
        ],
    ],

    'street' => [
        'name' => 'Street Drop',
        'tagline' => 'Loud, playful, Gen-Z',
        'description' => 'Neo-brutalist: thick outlines, hard shadows, sticker badges and huge type. Built for skins & wrap culture.',
        'dark' => false,
        'fonts' => 'https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;500;600;700&display=swap',
        'preview' => ['bg' => '#FFF4DE', 'surface' => '#FFFFFF', 'text' => '#111111', 'primary' => '#FF2E88', 'accent' => '#FFE600'],
        'gallery' => 'thumbs-bottom',
        'sections' => [
            ['type' => 'hero', 'settings' => ['slides' => [
                ['badge' => 'DROP 07 IS LIVE', 'title' => 'Skin it. Wrap it. Own it.', 'subtitle' => 'Carbon, matte & neon wraps cut for your exact phone. Zero bubbles, all attitude.', 'button_text' => 'Customise now', 'button_url' => '/shop?category=wraps-skins', 'image' => null],
            ]]],
            ['type' => 'product_rail', 'title' => 'Fresh drops', 'subtitle' => 'Straight off the cutter', 'settings' => ['source' => 'new', 'limit' => 8]],
            ['type' => 'categories', 'title' => 'Pick your poison', 'settings' => ['limit' => 6]],
            ['type' => 'flash_sale', 'title' => 'Steal it before it’s gone', 'settings' => ['limit' => 4]],
            ['type' => 'shop_by_device', 'title' => 'What’s your phone?', 'settings' => ['limit' => 10]],
            ['type' => 'bundles', 'title' => 'Combo packs', 'subtitle' => 'More gear, less money', 'settings' => ['limit' => 3]],
            ['type' => 'testimonials', 'title' => 'The crew says', 'settings' => ['items' => [
                ['name' => 'Zain', 'city' => 'Lahore', 'rating' => 5, 'text' => 'Neon wrap on my iPhone gets compliments every single day. 10/10.'],
                ['name' => 'Mahnoor', 'city' => 'Karachi', 'rating' => 5, 'text' => 'Fast, cheap, and the fit is insane. Already ordered two more.'],
                ['name' => 'Saad', 'city' => 'Peshawar', 'rating' => 5, 'text' => 'Matte black wrap + privacy glass combo = elite.'],
            ]]],
            ['type' => 'newsletter', 'title' => 'Join the drop list', 'subtitle' => 'Be first in line for limited wraps.', 'settings' => ['button_text' => 'I’m in']],
        ],
    ],

    'classic' => [
        'name' => 'Classic Retail',
        'tagline' => 'Clean, trustworthy, familiar',
        'description' => 'A polished, conventional shop layout: mega menu, trust badges, sidebar filters and clear calls to action.',
        'dark' => false,
        'fonts' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
        'preview' => ['bg' => '#FFFFFF', 'surface' => '#F5F7FB', 'text' => '#1A2333', 'primary' => '#1F5EFF', 'accent' => '#00B67A'],
        'gallery' => 'thumbs-bottom',
        'sections' => [
            ['type' => 'hero', 'settings' => ['slides' => [
                ['badge' => 'Pakistan’s accessory specialist', 'title' => 'Everything your phone needs, under one roof.', 'subtitle' => 'Cases, screen protection, chargers, cables and custom laser-cut wraps — delivered nationwide.', 'button_text' => 'Start shopping', 'button_url' => '/shop', 'image' => null],
                ['badge' => 'Free delivery', 'title' => 'Free shipping over the limit', 'subtitle' => 'Cash on Delivery available across Pakistan.', 'button_text' => 'Browse deals', 'button_url' => '/deals', 'image' => null],
            ]]],
            ['type' => 'features', 'settings' => ['items' => [
                ['icon' => 'ri-truck-line', 'title' => 'Fast delivery', 'text' => 'Nationwide in 2–4 days'],
                ['icon' => 'ri-secure-payment-line', 'title' => 'Secure checkout', 'text' => 'COD or bank transfer'],
                ['icon' => 'ri-arrow-go-back-line', 'title' => 'Easy returns', 'text' => '7-day exchange policy'],
                ['icon' => 'ri-scissors-cut-line', 'title' => 'Custom laser cutting', 'text' => 'Wraps & glass for any model'],
            ]]],
            ['type' => 'categories', 'title' => 'Popular categories', 'settings' => ['limit' => 6]],
            ['type' => 'product_rail', 'title' => 'Featured products', 'settings' => ['source' => 'featured', 'limit' => 8]],
            ['type' => 'flash_sale', 'title' => 'Deal of the day', 'settings' => ['limit' => 4]],
            ['type' => 'promo_banner', 'settings' => ['banners' => [
                ['title' => 'Screen protection that lasts', 'subtitle' => 'UV tempered, privacy & hydrogel options.', 'button_text' => 'Shop now', 'button_url' => '/shop?category=screen-protection', 'image' => null],
                ['title' => 'Power up on the go', 'subtitle' => 'Power banks and fast chargers.', 'button_text' => 'Shop now', 'button_url' => '/shop?category=power-banks', 'image' => null],
            ]]],
            ['type' => 'product_rail', 'title' => 'New arrivals', 'settings' => ['source' => 'new', 'limit' => 8]],
            ['type' => 'shop_by_device', 'title' => 'Shop by device', 'settings' => ['limit' => 8]],
            ['type' => 'brands', 'title' => 'Brands we carry', 'settings' => []],
            ['type' => 'testimonials', 'title' => 'What our customers say', 'settings' => ['items' => [
                ['name' => 'Ahmed T.', 'city' => 'Multan', 'rating' => 5, 'text' => 'Ordered on Monday, received on Wednesday. Exactly as described.'],
                ['name' => 'Fatima N.', 'city' => 'Rawalpindi', 'rating' => 5, 'text' => 'Helpful staff on WhatsApp, they confirmed my phone model before shipping.'],
                ['name' => 'Usman A.', 'city' => 'Lahore', 'rating' => 4, 'text' => 'Good prices and genuine chargers. Will buy again.'],
            ]]],
            ['type' => 'newsletter', 'title' => 'Subscribe to our newsletter', 'subtitle' => 'Get updates on new arrivals and special offers.', 'settings' => ['button_text' => 'Subscribe']],
        ],
    ],

];
