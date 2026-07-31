<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    RowBuddy\Queues\Infrastructure\QueuesServiceProvider::class,
    RowBuddy\QueuePresence\Infrastructure\QueuePresenceServiceProvider::class,
    RowBuddy\Auctions\Infrastructure\AuctionsServiceProvider::class,
    RowBuddy\Bids\Infrastructure\BidsServiceProvider::class,
    RowBuddy\Payments\Infrastructure\PaymentsServiceProvider::class,
    RowBuddy\Transfers\Infrastructure\TransfersServiceProvider::class,
    RowBuddy\Disputes\Infrastructure\DisputesServiceProvider::class,
    RowBuddy\Ratings\Infrastructure\RatingsServiceProvider::class,
    RowBuddy\Notifications\Infrastructure\NotificationsServiceProvider::class,
    RowBuddy\Administration\Infrastructure\AdministrationServiceProvider::class,
];
