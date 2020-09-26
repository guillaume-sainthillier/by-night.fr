vcl 4.0;

import std;
import directors;

include "/etc/varnish/fos/fos_ban.vcl";
include "/etc/varnish/fos/fos_custom_ttl.vcl";
include "/etc/varnish/fos/fos_purge.vcl";
include "/etc/varnish/fos/fos_refresh.vcl";

# by-night.fr
backend default {
    .host = "app";
    .port = "80";
}

acl invalidators {
    "app";
    "localhost";
    "127.0.0.1";
    "::1";
    "172.19.0.0"/16;
}

# Called at the beginning of a request, after the complete request has been received and parsed.
# Its purpose is to decide whether or not to serve the request, how to do it, and, if applicable,
# which backend to use.
# also used to modify the request
sub vcl_recv {

    # Pass real client ip to request
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # Normalize the header, remove the port (in case you're testing this on various TCP ports)
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");

    # Remove the proxy header (see https://httpoxy.org/#mitigate-varnish)
    unset req.http.proxy;

    # Normalize the query arguments
    set req.url = std.querysort(req.url);

    # FOS purge & ban
    call fos_purge_recv;
    call fos_ban_recv;
    call fos_refresh_recv;

    # Only deal with "normal" types
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "PATCH" &&
        req.method != "DELETE") {
        /* Non-RFC2616 or CONNECT which is weird. */
        return (synth(404, "Non-valid HTTP method!"));
    }

    # Only cache GET or HEAD requests. This makes sure the POST requests are always passed.
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # First remove the Google Analytics added parameters, useless for our backend
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
    }

    # Strip hash, server doesn't need it.
    if (req.url ~ "\#") {
        set req.url = regsub(req.url, "\#.*$", "");
    }

    # Strip a trailing ? if it exists
    if (req.url ~ "\?$") {
        set req.url = regsub(req.url, "\?$", "");
    }

    # Suppression de tous les cookies sur les pages publiques
    if( ! req.url ~ "^/(login|inscription|mot-de-passe-perdu|logout|profile|commentaire|espace-perso|social|_administration|_private|_profiler|_wdt)" ) {
        unset req.http.Cookie;
        set req.http.X-Cookie-State = "Deleted";
    }

    # Remove all cookies but no PHPSESSID
    if (req.http.Cookie) {
        set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
        set req.http.Cookie = regsuball(req.http.Cookie, ";(PHPSESSID|REMEMBERME|app_city)=", "; \1=");
        set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

        # Remove a ";" prefix in the cookie if present
        set req.http.Cookie = regsuball(req.http.Cookie, "^;\s*", "");

        # Are there cookies left with only spaces or that are empty?
        if (req.http.Cookie ~ "^\s*$") {
            unset req.http.Cookie;
        }

        set req.http.X-Cookie-State = "Vanished";
        set req.http.X-Cookie = req.http.Cookie;
    } else if(! req.http.X-Cookie-State) {
        set req.http.X-Cookie-State = "Empty";
    }

    # Send Surrogate-Capability headers to announce ESI support to backend
    set req.http.Surrogate-Capability = "abc=ESI/1.0";

     # Delegating static files to nginx
    if (req.url ~ "^[^?]*\.(7z|avi|bz2|flac|flv|gz|mka|mkv|mov|mp3|mp4|mpeg|mpg|ogg|ogm|opus|rar|tar|tgz|tbz|txz|wav|webm|xz|zip)(\?.*)?$") {
        unset req.http.Cookie;
        return (pass);
    }

    # Delegating static files to nginx
    if (req.url ~ "^[^?]*\.(7z|avi|bmp|bz2|css|csv|doc|docx|eot|flac|flv|gif|gz|ico|jpeg|jpg|js|less|mka|mkv|mov|mp3|mp4|mpeg|mpg|odt|otf|ogg|ogm|opus|pdf|png|ppt|pptx|rar|rtf|svg|svgz|swf|tar|tbz|tgz|ttf|txt|txz|wav|webm|webp|woff|woff2|xls|xlsx|xml|xz|zip)(\?.*)?$") {
        unset req.http.Cookie;
        return (pass);
    }

    return (hash);
}

# The data on which the hashing will take place
sub vcl_hash {
    # Called after vcl_recv to create a hash value for the request. This is used as a key
    # to look up the object in Varnish.

    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    # hash cookies for requests that have them
    if (req.http.Cookie) {
        hash_data(req.http.Cookie);
    }
}

# Called when a cache lookup is successful.
sub vcl_hit {

    if (obj.ttl >= 0s) {
        # A pure unadultered hit, deliver it
        return (deliver);
    }

    # https://www.varnish-cache.org/docs/trunk/users-guide/vcl-grace.html
    if (!std.healthy(req.backend_hint) && (obj.ttl + obj.grace > 0s)) {
        return (deliver);
    } else {
        return (miss);
    }

    # We have no fresh fish. Lets look at the stale ones.
    if (std.healthy(req.backend_hint)) {
        # Backend is healthy. Limit age to 10s.
        if (obj.ttl + 10s > 0s) {
            #set req.http.grace = "normal(limited)";
            return (deliver);
        } else {
            # No candidate for grace. Fetch a fresh object.
            return (miss);
        }
    } else {
        # backend is sick - use full grace
        if (obj.ttl + obj.grace > 0s) {
            #set req.http.grace = "full";
            return (deliver);
        } else {
            # no graced object.
        return (miss);
        }
    }

    # fetch & deliver once we get the result
    return (miss); # Dead code, keep as a safeguard
}

# Handle the HTTP request coming from our backend
sub vcl_backend_response {
	# Called after the response headers has been successfully retrieved from the backend.

	# Pause ESI request and remove Surrogate-Control header
	if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
		unset beresp.http.Surrogate-Control;
		set beresp.do_esi = true;
	}

	# Enable cache for all static files
    # The same argument as the static caches from above: monitor your cache size, if you get data nuked out of it, consider giving up the static file cache.
    # Before you blindly enable this, have a read here: https://ma.ttias.be/stop-caching-static-files/
    if (bereq.url ~ "^[^?]*\.(7z|avi|bmp|bz2|css|csv|doc|docx|eot|flac|flv|gif|gz|ico|jpeg|jpg|js|less|mka|mkv|mov|mp3|mp4|mpeg|mpg|odt|otf|ogg|ogm|opus|pdf|png|ppt|pptx|rar|rtf|svg|svgz|swf|tar|tbz|tgz|ttf|txt|txz|wav|webm|webp|woff|woff2|xls|xlsx|xml|xz|zip)(\?.*)?$") {
        unset beresp.http.set-cookie;
    }

    # Large static files are delivered directly to the end-user without
    # waiting for Varnish to fully read the file first.
    # Varnish 4 fully supports Streaming, so use streaming here to avoid locking.
    if (bereq.url ~ "^[^?]*\.(7z|avi|bz2|flac|flv|gz|mka|mkv|mov|mp3|mp4|mpeg|mpg|ogg|ogm|opus|rar|tar|tgz|tbz|txz|wav|webm|xz|zip)(\?.*)?$") {
        unset beresp.http.set-cookie;
    }

  	# Don't cache 50x responses
  	if (beresp.status == 500 || beresp.status == 502 || beresp.status == 503 || beresp.status == 504) {
		return (abandon);
  	}

    call fos_ban_backend_response;
    call fos_custom_ttl_backend_response;

    # Set 2min cache if unset for static files
    if (beresp.ttl <= 0s || beresp.http.Vary == "*") {
        set beresp.ttl = 0s; # Important, you shouldn't rely on this, SET YOUR HEADERS in the backend
        set beresp.uncacheable = true;
        return (deliver);
    }

  	# Allow stale content, in case the backend goes down.
  	# make Varnish keep all objects for 6 hours beyond their TTL
  	set beresp.grace = 6h;

  	return (deliver);
}


# The routine when we deliver the HTTP request to the user
# Last chance to modify headers that are sent to the client
sub vcl_deliver {
    # Called before a cached object is delivered to the client.

    # Add debug header to see if it's a HIT/MISS and the number of hits, disable when not needed
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    set resp.http.Server = "By Night";

	unset resp.http.Via;
	unset resp.http.X-Varnish;

	call fos_ban_deliver;

    if (resp.http.X-Cache-Debug) {
        set resp.http.X-Cookie-Debug = req.http.X-Cookie;
        set resp.http.X-Cookie-State = req.http.X-Cookie-State;
    }

    return (deliver);
}

sub vcl_synth {
    if (resp.status == 720) {
        # We use this special error status 720 to force redirects with 301 (permanent) redirects
        # To use this, call the following from anywhere in vcl_recv: return (synth(720, "http://host/new.html"));
        set resp.http.Location = resp.reason;
        set resp.status = 301;
    return (deliver);
    } elseif (resp.status == 721) {
        # And we use error status 721 to force redirects with a 302 (temporary) redirect
        # To use this, call the following from anywhere in vcl_recv: return (synth(720, "http://host/new.html"));
        set resp.http.Location = resp.reason;
        set resp.status = 302;
        return (deliver);
    }

    return (deliver);
}
