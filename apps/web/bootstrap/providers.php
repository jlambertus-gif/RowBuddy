<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    RowBuddy\Queues\Infrastructure\QueuesServiceProvider::class,
    RowBuddy\QueuePresence\Infrastructure\QueuePresenceServiceProvider::class,
];
