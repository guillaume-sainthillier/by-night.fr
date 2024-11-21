vcl 4.0;

import cookie;
import directors;
import std;
import xkey;

backend default {
    .host = "_BACKEND_HOST_";
    .port = "_BACKEND_PORT_";
}

# Hosts allowed to send BAN requests
acl invalidators {
    "localhost";
    "127.0.0.1";
    "::1";
    # docker-compose networks
    "172.0.0.0"/8;
}

###############################################################################
#                                                                             #
# Client side                                                                 #
#                                                                             #
###############################################################################

# Called at the beginning of a request, after the complete request has been received and parsed, after a restart
# or as the result of an ESI include.
#
# Its purpose is to decide whether or not to serve the request, possibly modify it and decide on how to process it
# further. A backend hint may be set as a default for the backend processing side.
sub vcl_recv {
    call app_req_forwarded;
    call app_req_host;
    call app_req_esi;
    call app_req_purge;
    call app_req_method;
    call app_req_static;
    call app_req_non_public;
    call app_req_cookie;
    call app_req_url;
    call app_req_proxy;
    call app_req_hitmiss;

    return (hash);
}

sub app_req_forwarded {
    # Pass real client ip to request
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        } else {
            set req.http.X-Forwarded-For = client.ip;
        }

        if (req.http.X-Forwarded-Proto == "https" ) {
            set req.http.X-Forwarded-Port = "443";
        } else {
            set req.http.X-Forwarded-Port = "_PUBLIC_PORT_";
        }
    }
}


sub app_req_method {
    if (req.method == "PRI") {
        # This will never happen in properly formed traffic.
        return (synth(405));
    }
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE" &&
        req.method != "PATCH") {
        # Non-RFC2616 or CONNECT which is weird.
        return (pipe);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        # We only deal with GET and HEAD by default.
        return (pass);
    }
}

sub app_req_purge {
    if (req.method == "PURGEKEYS") {
        if (!client.ip ~ invalidators) {
            return (synth(405, "Not allowed"));
        }

        set req.http.n-gone = 0;
        if (req.http.xkey) {
            set req.http.n-gone = xkey.purge(req.http.xkey);

            return (synth(200, "Purged " + req.http.n-gone + " objects"));
        }

        return (purge);
    }
}

sub app_req_static {
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
}

sub app_req_non_public {
    # flag all non-public requests
    if (req.url ~ "^/(login|login-social|inscription|verifier-email|mot-de-passe-perdu|logout|profile|commentaire|espace-perso|social|_administration|_private)" ) {
        set req.http.x-private = "1";
        unset req.http.x-public;
    } else {
        unset req.http.x-private;
        set req.http.x-public = "1";
    }

    set req.http.x-url = req.url;
}


sub app_req_cookie {
    if (req.http.x-private && req.http.cookie) {
        cookie.parse(req.http.cookie);
        cookie.keep("PHPSESSID,REMEMBERME,app_city");
        set req.http.cookie = cookie.get_string();

        # If empty, unset so the builtin VCL can consider it for caching.
        if (req.http.cookie == "") {
            unset req.http.cookie;
        }
        set req.http.X-Cookie-State = "Cleaned";
    } else {
        set req.http.X-Cookie-State = "Original";
    }
}

sub app_req_hitmiss {
    if (req.http.Cache-Control ~ "no-cache" && client.ip ~ invalidators) {
        set req.hash_always_miss = true;
    }
}

sub app_req_url {
    # Normalize the query arguments
    set req.url = std.querysort(req.url);

    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }

    # Strip hash, server doesn't need it.
    set req.url = regsub(req.url, "\#.*$", "");
}

sub app_req_esi {
    # Send Surrogate-Capability headers to announce ESI support to backend
    set req.http.Surrogate-Capability = "abc=ESI/1.0";
}

sub app_req_proxy {
    # https://httpoxy.org/#mitigate-varnish
    unset req.http.proxy;
}

sub app_req_host {
    if (req.http.host ~ "[[:upper:]]") {
        set req.http.host = req.http.host.lower();
    }

    if (!req.http.host &&
        req.esi_level == 0 &&
        req.proto == "HTTP/1.1") {
        # In HTTP/1.1, Host is required.
        return (synth(400));
    }

    set req.http.host = regsub(req.http.host, ":[0-9]+", "");
}

# Called after vcl_recv to create a hash value for the request. This is used as a key to look up the object in Varnish.
sub vcl_hash {
    hash_data(req.url);

    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }

    if (
        req.http.cookie
        && req.http.x-private
    ) {
        hash_data(req.http.cookie);
    }
}

# Called before any object except a vcl_synth result is delivered to the client.
sub vcl_deliver {
    call app_resp_headers;
    call app_resp_xkey;
    call app_resp_cachehits;
    call app_resp_custom_ttl;

    return (deliver);
}

sub app_resp_headers {
    set resp.http.Server = "By Night";

	unset resp.http.Via;
	unset resp.http.X-Varnish;
}

sub app_resp_xkey {
    if ("1" != std.getenv("DEBUG")) {
        // Remove tag headers when delivering to non debug client
        unset resp.http.xkey;
        unset resp.http.X-Cache-N-Gone;
    }
}

sub app_resp_cachehits {
    # Add X-Cache header if debugging is enabled
    if ("1" == std.getenv("DEBUG")) {
        if (obj.hits > 0) {
            set resp.http.X-Cache-Status = "HIT";
            set resp.http.X-Cache-Hits = obj.hits;
        } elseif (obj.uncacheable) {
            set resp.http.X-Cache-Status = "BYPASSED";
        } else {
            set resp.http.X-Cache-Status = "MISSED";
        }
    }
}

sub app_resp_custom_ttl {
    if ("1" != std.getenv("DEBUG")) {
        unset resp.http.X-Reverse-Proxy-TTL;
    }
}


###############################################################################
#                                                                             #
# Backend side                                                                #
#                                                                             #
###############################################################################

# Called after the response headers have been successfully retrieved from the backend.
sub vcl_backend_response {
    call app_beresp_esi;

    if (bereq.uncacheable) {
        return (deliver);
    }

    call app_beresp_esi;
    call app_beresp_custom_ttl;
    call app_beresp_grace;
    call app_beresp_stale;
    call app_beresp_cookie;
    call app_beresp_vary;

    return (deliver);
}

sub app_beresp_esi {
	# Pause ESI request and remove Surrogate-Control header
	if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
		unset beresp.http.Surrogate-Control;
		set beresp.do_esi = true;
	}
}
sub app_beresp_custom_ttl {
    if (beresp.http.X-Reverse-Proxy-TTL) {
        set beresp.ttl = std.duration(beresp.http.X-Reverse-Proxy-TTL + "s", 0s);
    }
}

sub app_beresp_grace {
    # Allow stale content, in case the backend goes down.
    # make Varnish keep all objects for 6 hours beyond their TTL
    set beresp.grace = 6h;
}

sub app_beresp_stale {
    if (beresp.ttl <= 0s) {
        call app_beresp_hitmiss;

        return (deliver);
    }
}

sub app_beresp_cookie {
    if (beresp.http.Set-Cookie) {
        call app_beresp_hitmiss;

        return (deliver);
    }
}

sub app_beresp_vary {
    if (beresp.http.Vary == "*") {
        call app_beresp_hitmiss;

        return (deliver);
    }
}

sub app_beresp_hitmiss {
    # Mark as "Hit-For-Miss" for the next 2 minutes
    set beresp.ttl = 120s;
    set beresp.uncacheable = true;
}

# This subroutine is called if we fail the backend fetch or if max_retries has been exceeded.
sub vcl_backend_error {
    return (deliver);
}
