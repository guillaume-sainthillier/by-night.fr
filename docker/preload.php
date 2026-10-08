<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// opcache.preload of the image (docker/php.ini): runs once at startup, in FrankenPHP's main thread, before the worker
// threads run any PHP.

require '/app/config/preload.php';

// libvips renders the thumbnails in production (config/packages/picasso.yaml), and its vips_init() is not thread-safe:
// a thread calling it while another one is still initializing returns at once, and php-vips then caches GType 0 for
// "VipsBlob" for the life of the worker thread, which fails every render (BY-NIGHTFR-6CF). Initialized here, libvips
// is ready before any thread renders: their own vips_init() returns at once.
try {
    if (0 !== FFI::cdef('int vips_init(const char *argv0);', 'libvips.so.42')->vips_init('')) {
        error_log('vips_init() failed: the thumbnails will not render');
    }
} catch (FFI\Exception $e) {
    error_log(\sprintf('libvips could not be loaded, the thumbnails will not render: %s', $e->getMessage()));
}
