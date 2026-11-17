<?php

use Inertia\Testing\AssertableInertia as Assert;

test('inertia provides the named routes required by navigation', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('ziggy.url')
        ->where('ziggy.routes.dashboard.uri', 'dashboard')
        ->where('ziggy.routes.users.uri', 'users')
        ->where('ziggy.routes', fn ($routes) => $routes['permissions.index']['uri'] === 'permissions')
        ->where('ziggy.routes.logout.methods', ['POST'])
    );
});
