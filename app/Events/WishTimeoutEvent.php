<?php

namespace App\Events;

use App\Models\Wish;

class WishTimeoutEvent extends Event
{
    public $wish;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Wish $wish)
    {
        $this->wish = $wish;
    }
}
