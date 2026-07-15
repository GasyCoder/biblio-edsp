<?php

it('redirects the application root to login', function () {
    $this->get('/')->assertRedirect('/login');
});
