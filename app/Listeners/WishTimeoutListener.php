<?php

namespace App\Listeners;

use App\Events\ExampleEvent;
use App\Events\WishTimeoutEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class WishTimeoutListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  WishTimeoutEvent  $event
     * @return void
     */
    public function handle(WishTimeoutEvent $event)
    {

    }
}
