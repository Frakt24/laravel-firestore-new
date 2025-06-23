<?php

namespace Frakt24\LaravelFirestore\Facades;

use Illuminate\Support\Facades\Facade;

class Firestore extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'firestore';
    }
}
