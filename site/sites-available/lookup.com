# ============================================================
#  lookupfriseur.com  |  www  |  dashboard
#  /etc/nginx/sites-available/lookup.com
# ============================================================

# ---------- HTTP (80) : ACME challenge + redirect to HTTPS ----------
server {
    listen 80;
    listen [::]:80;

    server_name lookupfriseur.com
                www.lookupfriseur.com
                dashboard.lookupfriseur.com;

    # Let's Encrypt HTTP-01 validation must stay reachable over plain HTTP
    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
        default_type "text/plain";
        allow all;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# ---------- HTTPS (443) ----------
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;

    server_name lookupfriseur.com
                www.lookupfriseur.com
                dashboard.lookupfriseur.com;

    root /var/www/lookup.com/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 20M;

    # --- TLS ---
    ssl_certificate     /etc/letsencrypt/live/lookupfriseur.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/lookupfriseur.com/privkey.pem;   # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf;                           # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;                             # managed by Certbot

    # --- Security headers ---
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    # Enable HSTS only after every subdomain is on HTTPS (browsers cache it hard):
    # add_header Strict-Transport-Security "max-age=31536000" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ \.php$ { return 404; }

    location ~ /\.(?!well-known).* { deny all; }
}
