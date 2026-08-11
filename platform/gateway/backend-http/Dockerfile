FROM nginx:stable-alpine3.24

COPY platform/gateway/backend-http/nginx.conf /etc/nginx/nginx.conf
COPY backend/laravel-core/public/ /var/www/html/public/

# Laravel creates the same link in its writable application container. Nginx
# owns a matching image-local link whose target is the shared read-only volume.
RUN apk upgrade --no-cache \
    && rm -f /etc/nginx/conf.d/default.conf /var/www/html/public/storage \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 8080

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]
