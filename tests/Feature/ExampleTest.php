<?php

test('the home page redirects to the overview', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('overview'));
});
